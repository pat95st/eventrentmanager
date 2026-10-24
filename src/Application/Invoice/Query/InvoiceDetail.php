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
     */
    public function __construct(
        public readonly Invoice $invoice,
        public readonly ?Customer $customer,
        public readonly array $items,
        public readonly array $laborItems,
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
        );
    }
}
