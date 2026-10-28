<?php

declare(strict_types=1);

namespace App\Application\OrderConfirmation\Query;

use App\Domain\Customer\Customer;
use App\Domain\Customer\CustomerContact;
use App\Domain\OrderConfirmation\OrderConfirmation;
use App\Domain\OrderConfirmation\OrderConfirmationFreeTextItem;
use App\Domain\OrderConfirmation\OrderConfirmationSectionText;
use App\Domain\Pricing\DocumentTotals;

final class OrderConfirmationDetail
{
    /**
     * @param OrderConfirmationItemWithArticle[] $items
     * @param OrderConfirmationFreeTextItem[] $freeTextItems
     * @param OrderConfirmationSectionText[] $sectionTexts
     */
    public function __construct(
        public readonly OrderConfirmation $orderConfirmation,
        public readonly ?Customer $customer,
        public readonly array $items,
        public readonly array $freeTextItems = [],
        public readonly array $sectionTexts = [],
        // Ansprechpartner-Auswahl (Migration 104), siehe
        // InvoiceDetail::$contact für die identische Begründung/Semantik.
        public readonly ?CustomerContact $contact = null,
    ) {
    }

    /** Zwischensumme reiner Mietpreis-Positionen (VOR Zubehörpauschale/Rabatt). */
    public function grandTotal(): float
    {
        $total = 0.0;
        foreach ($this->items as $entry) {
            $total += $entry->lineTotal();
        }

        return $total;
    }

    /** Summe aller Freitext-Posten — fließt mit in die Basis für Zubehörpauschale/Kombirabatt ein, siehe totals(). */
    public function freeTextTotal(): float
    {
        $total = 0.0;
        foreach ($this->freeTextItems as $entry) {
            $total += $entry->lineTotal();
        }

        return $total;
    }

    /**
     * Vollständige Summenberechnung, siehe App\Domain\Pricing\
     * DocumentTotals. Eine Auftragsbestätigung kennt (wie ein Angebot) kein
     * eigenes Kleinunternehmer-/Steuersatz-Feld — isSmallBusiness daher
     * bewusst fest auf true, siehe OfferDetail::totals() für die identische
     * Begründung.
     */
    public function totals(): DocumentTotals
    {
        return DocumentTotals::calculate(
            rentalNetTotal: $this->grandTotal(),
            accessoryFlatRateEnabled: $this->orderConfirmation->accessoryFlatRateEnabled,
            accessoryFlatRatePercent: $this->orderConfirmation->accessoryFlatRatePercent,
            discountType: $this->orderConfirmation->discountType,
            discountValue: $this->orderConfirmation->discountValue,
            laborTimeTotal: 0.0, // Auftragsbestätigungen kennen keine Arbeitszeit-Positionen
            isSmallBusiness: true,
            vatRatePercent: '0',
            // Freitext-Posten gehören wie bei Angebot/Rechnung zur Basis für
            // Zubehörpauschale/Kombirabatt (Nutzerentscheidung, siehe
            // DocumentTotals::calculate()) — wurden hier zuvor fälschlich
            // als laborTimeTotal NACH dem Rabatt addiert, abweichend von der
            // Live-Summe im Formular.
            freeTextTotal: $this->freeTextTotal(),
        );
    }

    /**
     * Mischt reguläre Positionen, Freitext-Posten und Zwischentexte in EINE,
     * nach sortOrder sortierte Reihenfolge (orderedRows()-Konzept, siehe
     * OrderConfirmationRow) — für show.php und OrderConfirmationPdfGenerator.
     *
     * @return OrderConfirmationRow[]
     */
    public function orderedRows(): array
    {
        $rows = [];
        foreach ($this->items as $item) {
            $rows[] = OrderConfirmationRow::forItem($item);
        }
        foreach ($this->freeTextItems as $item) {
            $rows[] = OrderConfirmationRow::forFreeTextItem($item);
        }
        foreach ($this->sectionTexts as $sectionText) {
            $rows[] = OrderConfirmationRow::forSectionText($sectionText);
        }

        usort($rows, static fn (OrderConfirmationRow $a, OrderConfirmationRow $b) => $a->sortOrder <=> $b->sortOrder);

        return $rows;
    }
}
