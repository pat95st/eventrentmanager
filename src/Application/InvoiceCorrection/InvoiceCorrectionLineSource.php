<?php

declare(strict_types=1);

namespace App\Application\InvoiceCorrection;

use App\Application\Invoice\Query\GetInvoice;
use App\Application\Invoice\Query\GetInvoiceHandler;
use App\Application\Invoice\Query\InvoiceDetail;
use App\Application\Invoice\Query\InvoiceRow;
use App\Domain\Invoice\InvoiceFreeTextItem;
use App\Domain\InvoiceCorrection\InvoiceCorrectionRepositoryInterface;
use App\Domain\InvoiceCorrection\InvoiceCorrectionStatus;
use App\Domain\InvoiceCorrection\InvoiceCorrectionType;
use App\Domain\Settings\InvoiceSettingsRepositoryInterface;

/**
 * Leitet aus einer Rechnung die Positionen für Rechnungskorrekturen ab
 * (Migration 121): jede Position mit Menge und Einzelpreis netto, dazu
 * Zubehörpauschale, Kombirabatt, Pauschalpreis und Versandkosten als eigene
 * Zeilen. Die Summe entspricht immer dem Nettobetrag der Rechnung (eine
 * eventuelle Rundungsdifferenz wird als eigene Zeile ausgewiesen).
 *
 * - forInvoice(): Positionen der Rechnung (positiv) – Auswahl "Position aus
 *   Rechnung übernehmen" bei der Teilkorrektur.
 * - cancellationLines(): alle Positionen negativ plus bereits ausgestellte
 *   Teilkorrekturen negativ – Ergebnis einer Stornorechnung ist 0 €.
 */
final class InvoiceCorrectionLineSource
{
    public function __construct(
        private readonly GetInvoiceHandler $getInvoice,
        private readonly InvoiceSettingsRepositoryInterface $invoiceSettings,
        private readonly InvoiceCorrectionRepositoryInterface $corrections,
    ) {
    }

    public function detail(string $invoiceId): InvoiceDetail
    {
        return $this->getInvoice->handle(new GetInvoice($invoiceId));
    }

    /** @return list<array{description: string, quantity: string, unitPrice: string}> */
    public function forInvoice(InvoiceDetail $detail): array
    {
        $invoice = $detail->invoice;
        $totals = $detail->totals($this->invoiceSettings->get());
        $lines = [];
        $add = static function (array &$lines, string $description, float $quantity, float $unitPrice): void {
            $lines[] = [
                'description' => mb_substr(trim($description), 0, 500),
                'quantity' => number_format($quantity, 2, '.', ''),
                'unitPrice' => number_format($unitPrice, 2, '.', ''),
            ];
        };

        $flatRate = $invoice->flatRateEnabled && $invoice->flatRateAmount !== null;
        foreach ($detail->orderedRows() as $row) {
            if ($row->type === InvoiceRow::TYPE_SECTION) {
                continue;
            }
            if ($row->type === InvoiceRow::TYPE_FREE_TEXT) {
                $item = $row->data;
                assert($item instanceof InvoiceFreeTextItem);
                $add($lines, $item->description, (float) $item->quantity, (float) $item->unitPrice);
                continue;
            }
            $entry = $row->data;
            if ($flatRate && $entry->item->isRentalItem) {
                continue; // in der Pauschale enthalten
            }
            $add($lines, $entry->displayName(), (float) $entry->item->quantity, (float) $entry->item->unitPricePerDay);
        }
        if ($flatRate) {
            $add($lines, 'Pauschalpreis Mietpositionen', 1, (float) $invoice->flatRateAmount);
        }
        if ($totals->accessoryFlatRateAmount >= 0.005) {
            $percent = rtrim(rtrim(number_format((float) $invoice->accessoryFlatRatePercent, 2, ',', ''), '0'), ',');
            $add($lines, 'Zubehörpauschale (' . $percent . ' %)', 1, $totals->accessoryFlatRateAmount);
        }
        if ($totals->discountAmount >= 0.005) {
            $add($lines, 'Kombirabatt', 1, -$totals->discountAmount);
        }
        foreach ($detail->laborItems as $laborEntry) {
            $name = $laborEntry->laborTimeArticle->name . ($laborEntry->user !== null ? ' – ' . $laborEntry->user->displayName() : '');
            $add($lines, $name, (float) $laborEntry->item->hours, (float) $laborEntry->item->unitRatePerHour);
        }
        if ($totals->shippingAmount >= 0.005) {
            $add($lines, 'Versandkosten', 1, $totals->shippingAmount);
        }

        $sum = 0.0;
        foreach ($lines as $line) {
            $sum += round((float) $line['quantity'] * (float) $line['unitPrice'], 2);
        }
        $difference = round($totals->netTotal - $sum, 2);
        if (abs($difference) >= 0.005) {
            $add($lines, 'Rundungsdifferenz', 1, $difference);
        }

        return $lines;
    }

    /** @return list<array{description: string, quantity: string, unitPrice: string}> */
    public function cancellationLines(InvoiceDetail $detail, ?string $excludeCorrectionId = null): array
    {
        $lines = array_map(static fn (array $line): array => self::negate($line), $this->forInvoice($detail));

        foreach (array_reverse($this->corrections->findByInvoice($detail->invoice->id)) as $correction) {
            if ($correction->id === $excludeCorrectionId || $correction->status !== InvoiceCorrectionStatus::Issued || $correction->type !== InvoiceCorrectionType::Partial) {
                continue;
            }
            foreach ($correction->lines as $line) {
                $lines[] = self::negate([
                    'description' => mb_substr('Korrektur ' . $correction->correctionNumber . ': ' . $line->description, 0, 500),
                    'quantity' => $line->quantity,
                    'unitPrice' => $line->unitPrice,
                ]);
            }
        }

        return $lines;
    }

    /**
     * USt der Stornorechnung als exakte Umkehrung: −(USt der Rechnung +
     * USt aller ausgestellten Teilkorrekturen), auf den Cent genau.
     */
    public function cancellationTax(InvoiceDetail $detail, ?string $excludeCorrectionId = null): string
    {
        $tax = $detail->totals($this->invoiceSettings->get())->taxAmount;
        foreach ($this->corrections->findByInvoice($detail->invoice->id) as $correction) {
            if ($correction->id !== $excludeCorrectionId && $correction->status === InvoiceCorrectionStatus::Issued && $correction->type === InvoiceCorrectionType::Partial) {
                $tax += $correction->taxAmount();
            }
        }
        $tax = -round($tax, 2);

        return number_format(abs($tax) < 0.005 ? 0.0 : $tax, 2, '.', '');
    }

    /** @param array{description: string, quantity: string, unitPrice: string} $line */
    private static function negate(array $line): array
    {
        $value = -(float) $line['unitPrice'];
        $line['unitPrice'] = number_format(abs($value) < 0.005 ? 0.0 : $value, 2, '.', '');

        return $line;
    }
}
