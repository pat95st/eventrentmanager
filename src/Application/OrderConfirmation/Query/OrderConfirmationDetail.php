<?php

declare(strict_types=1);

namespace App\Application\OrderConfirmation\Query;

use App\Domain\Customer\Customer;
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

    /** Summe aller Freitext-Posten — erscheint wie Offer::laborTimeTotal() immer NACH dem Kombirabatt, siehe totals(). */
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
            laborTimeTotal: $this->freeTextTotal(),
            isSmallBusiness: true,
            vatRatePercent: '0',
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
