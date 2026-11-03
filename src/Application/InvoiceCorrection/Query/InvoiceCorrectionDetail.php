<?php

declare(strict_types=1);

namespace App\Application\InvoiceCorrection\Query;

use App\Application\Invoice\Query\InvoiceDetail;
use App\Domain\InvoiceCorrection\InvoiceCorrection;

/**
 * Rechnungskorrektur mit Rechnung (Detailseite, Formular, PDF). $correction
 * ist null beim Anlegen einer neuen Korrektur.
 */
final class InvoiceCorrectionDetail
{
    /**
     * @param list<array{description: string, quantity: string, unitPrice: string}> $invoiceLines Positionen der Rechnung (positiv)
     * @param InvoiceCorrection[] $issuedCorrections bereits ausgestellte Korrekturen dieser Rechnung (ohne $correction)
     */
    public function __construct(
        public readonly ?InvoiceCorrection $correction,
        public readonly InvoiceDetail $invoice,
        public readonly float $invoiceNetTotal,
        public readonly float $invoiceGrossTotal,
        public readonly array $invoiceLines,
        public readonly array $issuedCorrections,
    ) {
    }

    /** Rechnungsbetrag brutto inklusive aller bisher ausgestellten Korrekturen (ohne diese). */
    public function grossBefore(): float
    {
        $total = $this->invoiceGrossTotal;
        foreach ($this->issuedCorrections as $other) {
            $total += $other->grossTotal();
        }

        return round($total, 2);
    }

    /** Rechnungsbetrag brutto nach dieser Korrektur. */
    public function grossAfter(): float
    {
        return round($this->grossBefore() + ($this->correction?->grossTotal() ?? 0.0), 2);
    }
}
