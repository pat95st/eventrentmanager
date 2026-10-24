<?php

declare(strict_types=1);

namespace App\Application\Invoice\Query;

use App\Domain\Customer\Customer;
use App\Domain\Invoice\Invoice;
use App\Domain\Pricing\DocumentTotals;
use App\Domain\Settings\InvoiceSettings;

final class InvoiceDetail
{
    /**
     * @param InvoiceItemWithArticle[] $items
     * @param InvoiceLaborItemWithArticle[] $laborItems
     * @param \App\Domain\Invoice\InvoiceFreeTextItem[] $freeTextItems
     * @param \App\Domain\Invoice\InvoiceSectionText[] $sectionTexts
     */
    public function __construct(
        public readonly Invoice $invoice,
        public readonly ?Customer $customer,
        public readonly array $items,
        public readonly array $laborItems,
        public readonly array $freeTextItems = [],
        public readonly array $sectionTexts = [],
    ) {
    }

    /** Zwischensumme reiner Mietpreis-Positionen (VOR Zubehörpauschale/Rabatt) — siehe totals() für die vollständige Berechnung. */
    public function netTotal(): float
    {
        $total = 0.0;
        foreach ($this->items as $entry) {
            $total += $entry->lineTotal();
        }

        return $total;
    }

    /** Historisch/legacy Alias — siehe offers/show.php-Äquivalent (grandTotal). Für Rechnungen bewusst als netTotal() benannt, da hier zusätzlich Steuer ausgewiesen wird. */
    public function grandTotal(): float
    {
        return $this->netTotal();
    }

    /** Summe aller Arbeitszeit-Positionen (erscheint auf der Rechnung immer NACH dem Kombirabatt) — siehe totals(). */
    public function laborTimeTotal(): float
    {
        $total = 0.0;
        foreach ($this->laborItems as $entry) {
            $total += $entry->lineTotal();
        }

        return $total;
    }

    /** Summe aller Freitext-Posten (orderedRows()-Konzept) — erscheint wie laborTimeTotal() immer NACH dem Kombirabatt, siehe totals(). */
    public function freeTextTotal(): float
    {
        $total = 0.0;
        foreach ($this->freeTextItems as $entry) {
            $total += $entry->lineTotal();
        }

        return $total;
    }

    /**
     * Vollständige Summenberechnung (Mietpreise -> Zubehörpauschale ->
     * Kombirabatt -> Netto/USt/Brutto), siehe App\Domain\Pricing\DocumentTotals.
     * isSmallBusiness/vatRatePercent kommen vom Snapshot der Rechnung
     * (sobald versendet), sonst als Vorschau von den aktuellen InvoiceSettings
     * (identisches Muster wie bisher in invoices/show.php/InvoicePdfGenerator).
     */
    public function totals(InvoiceSettings $invoiceSettings): DocumentTotals
    {
        return DocumentTotals::calculate(
            rentalNetTotal: $this->netTotal(),
            accessoryFlatRateEnabled: $this->invoice->accessoryFlatRateEnabled,
            accessoryFlatRatePercent: $this->invoice->accessoryFlatRatePercent,
            discountType: $this->invoice->discountType,
            discountValue: $this->invoice->discountValue,
            laborTimeTotal: $this->laborTimeTotal(),
            isSmallBusiness: $this->invoice->isSmallBusiness ?? $invoiceSettings->isSmallBusiness,
            vatRatePercent: $this->invoice->vatRatePercent ?? $invoiceSettings->vatRatePercent,
            shippingAmount: $this->invoice->shippingAmount !== null ? (float) $this->invoice->shippingAmount : 0.0,
            freeTextTotal: $this->freeTextTotal(),
        );
    }

    /**
     * Mischt reguläre Positionen, Freitext-Posten und Zwischentexte in EINE,
     * nach sortOrder sortierte Reihenfolge (orderedRows()-Konzept, siehe
     * InvoiceRow) — für invoices/show.php und InvoicePdfGenerator.
     * Arbeitszeit-Positionen bleiben davon bewusst unberührt und werden
     * weiterhin separat über $laborItems gerendert.
     *
     * @return InvoiceRow[]
     */
    public function orderedRows(): array
    {
        $rows = [];
        foreach ($this->items as $item) {
            $rows[] = InvoiceRow::forItem($item);
        }
        foreach ($this->freeTextItems as $item) {
            $rows[] = InvoiceRow::forFreeTextItem($item);
        }
        foreach ($this->sectionTexts as $sectionText) {
            $rows[] = InvoiceRow::forSectionText($sectionText);
        }

        usort($rows, static fn (InvoiceRow $a, InvoiceRow $b) => $a->sortOrder <=> $b->sortOrder);

        return $rows;
    }
}
