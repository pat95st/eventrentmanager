<?php

declare(strict_types=1);

namespace App\Application\Reporting\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Invoice\Invoice;
use App\Domain\Invoice\InvoiceRepositoryInterface;
use App\Domain\Pricing\DocumentTotals;
use App\Domain\Receipt\Receipt;
use App\Domain\Receipt\ReceiptRepositoryInterface;
use App\Domain\Settings\InvoiceSettings;
use App\Domain\Settings\InvoiceSettingsRepositoryInterface;
use DateTimeImmutable;

/**
 * Baut die Einnahmen-/Ausgabenübersicht für ein Kalenderjahr — bewusst nach
 * dem Zufluss-/Abfluss-Prinzip (Zahlungsdatum, siehe
 * InvoiceRepositoryInterface::findPaidBetween()/
 * ReceiptRepositoryInterface::findPaidBetween()), der üblichen Grundlage
 * einer Einnahmen-Überschuss-Rechnung für kleine Betriebe: nur was
 * tatsächlich bezahlt wurde, zählt, unabhängig vom Rechnungs-/Belegdatum.
 * Offene (nicht bezahlte) Rechnungen/Belege fließen NICHT ein — stornierte
 * Rechnungen (InvoiceStatus::Cancelled) ohnehin nicht, da sie nie den
 * Status "paid" erreichen können (siehe MarkInvoiceCancelledHandler).
 *
 * Einnahmen: Bruttosumme jeder bezahlten Rechnung über
 * App\Domain\Pricing\DocumentTotals::calculate() (dieselbe Berechnung wie
 * in InvoiceDetail::totals()/InvoicePdfGenerator) — Rechnungen führen
 * keinen gespeicherten Gesamtbetrag, er wird aus den Positionen berechnet.
 * Ausgaben: Summe des Beleg-Betrags (Receipt::$amount), zusätzlich nach
 * Kategorie aufgeschlüsselt (siehe ReceiptCategory).
 */
final class GetIncomeExpenseOverviewHandler implements QueryHandlerInterface
{
    public function __construct(
        private readonly InvoiceRepositoryInterface $invoices,
        private readonly ReceiptRepositoryInterface $receipts,
        private readonly InvoiceSettingsRepositoryInterface $invoiceSettings,
    ) {
    }

    public function handle(QueryInterface $query): IncomeExpenseOverview
    {
        assert($query instanceof GetIncomeExpenseOverview);

        $year = $query->year;
        $from = new DateTimeImmutable($year . '-01-01');
        $to = new DateTimeImmutable($year . '-12-31');

        $invoiceSettings = $this->invoiceSettings->get();

        $incomeByMonth = array_fill(1, 12, 0.0);
        foreach ($this->invoices->findPaidBetween($from, $to) as $invoice) {
            assert($invoice instanceof Invoice);
            $month = (int) $invoice->paidAt?->format('n');
            if ($month < 1 || $month > 12) {
                continue;
            }

            $incomeByMonth[$month] += $this->grossTotalFor($invoice, $invoiceSettings);
        }

        $expenseByMonth = array_fill(1, 12, 0.0);
        $expenseByCategory = [];
        foreach ($this->receipts->findPaidBetween($from, $to) as $receipt) {
            assert($receipt instanceof Receipt);
            $month = (int) $receipt->paidAt?->format('n');
            if ($month < 1 || $month > 12) {
                continue;
            }

            $amount = (float) $receipt->amount;
            $expenseByMonth[$month] += $amount;
            $categoryKey = $receipt->category->value;
            $expenseByCategory[$categoryKey] = ($expenseByCategory[$categoryKey] ?? 0.0) + $amount;
        }

        $months = [];
        for ($m = 1; $m <= 12; $m++) {
            $months[] = new IncomeExpenseMonth($m, $incomeByMonth[$m], $expenseByMonth[$m]);
        }

        return new IncomeExpenseOverview($year, $months, $expenseByCategory);
    }

    private function grossTotalFor(Invoice $invoice, InvoiceSettings $invoiceSettings): float
    {
        $items = $this->invoices->findItemsByInvoice($invoice->id);
        $laborItems = $this->invoices->findLaborItemsByInvoice($invoice->id);

        $rentalNetTotal = 0.0;
        foreach ($items as $item) {
            $rentalNetTotal += $item->lineTotal();
        }

        $laborTimeTotal = 0.0;
        foreach ($laborItems as $laborItem) {
            $laborTimeTotal += $laborItem->lineTotal();
        }

        $totals = DocumentTotals::calculate(
            rentalNetTotal: $rentalNetTotal,
            accessoryFlatRateEnabled: $invoice->accessoryFlatRateEnabled,
            accessoryFlatRatePercent: $invoice->accessoryFlatRatePercent,
            discountType: $invoice->discountType,
            discountValue: $invoice->discountValue,
            laborTimeTotal: $laborTimeTotal,
            isSmallBusiness: $invoice->isSmallBusiness ?? $invoiceSettings->isSmallBusiness,
            vatRatePercent: $invoice->vatRatePercent ?? $invoiceSettings->vatRatePercent,
        );

        return $totals->grossTotal;
    }
}
