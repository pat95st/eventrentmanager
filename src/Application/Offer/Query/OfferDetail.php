<?php

declare(strict_types=1);

namespace App\Application\Offer\Query;

use App\Domain\Customer\Customer;
use App\Domain\Offer\Offer;
use App\Domain\Pricing\DocumentTotals;

final class OfferDetail
{
    /**
     * @param OfferItemWithArticle[] $items
     * @param OfferLaborItemWithArticle[] $laborItems
     * @param OfferSaleItemWithArticle[] $saleItems
     * @param \App\Domain\Offer\OfferFreeTextItem[] $freeTextItems
     * @param \App\Domain\Offer\OfferSectionText[] $sectionTexts
     */
    public function __construct(
        public readonly Offer $offer,
        public readonly ?Customer $customer,
        public readonly array $items,
        public readonly array $laborItems,
        public readonly array $saleItems = [],
        public readonly array $freeTextItems = [],
        public readonly array $sectionTexts = [],
    ) {
    }

    /** Zwischensumme reiner Mietpreis-Positionen (VOR Zubehörpauschale/Rabatt) — siehe totals() für die vollständige Berechnung. */
    public function grandTotal(): float
    {
        $total = 0.0;
        foreach ($this->items as $entry) {
            $total += $entry->lineTotal();
        }

        return $total;
    }

    /** Summe aller Arbeitszeit-Positionen (erscheint auf Angebot/Rechnung immer NACH dem Kombirabatt) — siehe totals(). */
    public function laborTimeTotal(): float
    {
        $total = 0.0;
        foreach ($this->laborItems as $entry) {
            $total += $entry->lineTotal();
        }

        return $total;
    }

    /** Summe aller Verkaufspositionen (erscheint wie die Arbeitszeit immer NACH dem Kombirabatt, siehe totals()). */
    public function saleTotal(): float
    {
        $total = 0.0;
        foreach ($this->saleItems as $entry) {
            $total += $entry->lineTotal();
        }

        return $total;
    }

    /** Summe aller Freitext-Posten (orderedRows()-Konzept) — erscheint wie laborTimeTotal()/saleTotal() immer NACH dem Kombirabatt, siehe totals(). */
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
     * Kombirabatt -> Nettosumme), siehe App\Domain\Pricing\DocumentTotals.
     * Ein Angebot kennt (anders als eine Rechnung) KEIN eigenes
     * Kleinunternehmer-/Steuersatz-Feld — es ist ein unverbindlicher
     * Kostenvoranschlag ohne Steuerausweis (bereits vor diesem Feature so,
     * siehe offers/show.php). isSmallBusiness wird daher bewusst fest auf
     * true gesetzt, wodurch taxAmount immer 0 bleibt und grossTotal ===
     * netTotal — das bisherige Verhalten bleibt unverändert erhalten.
     */
    public function totals(): DocumentTotals
    {
        return DocumentTotals::calculate(
            rentalNetTotal: $this->grandTotal(),
            accessoryFlatRateEnabled: $this->offer->accessoryFlatRateEnabled,
            accessoryFlatRatePercent: $this->offer->accessoryFlatRatePercent,
            discountType: $this->offer->discountType,
            discountValue: $this->offer->discountValue,
            laborTimeTotal: $this->laborTimeTotal(),
            isSmallBusiness: true,
            vatRatePercent: '0',
            saleTotal: $this->saleTotal(),
            freeTextTotal: $this->freeTextTotal(),
        );
    }

    /**
     * Mischt reguläre Mietpositionen, Freitext-Posten und Zwischentexte in
     * EINE, nach sortOrder sortierte Reihenfolge (orderedRows()-Konzept,
     * siehe OfferRow) — für offers/show.php und OfferPdfGenerator.
     * Arbeitszeit-/Verkaufspositionen bleiben davon bewusst unberührt (siehe
     * Klassendoc) und werden weiterhin separat über $laborItems/$saleItems
     * gerendert.
     *
     * @return OfferRow[]
     */
    public function orderedRows(): array
    {
        $rows = [];
        foreach ($this->items as $item) {
            $rows[] = OfferRow::forItem($item);
        }
        foreach ($this->freeTextItems as $item) {
            $rows[] = OfferRow::forFreeTextItem($item);
        }
        foreach ($this->sectionTexts as $sectionText) {
            $rows[] = OfferRow::forSectionText($sectionText);
        }

        usort($rows, static fn (OfferRow $a, OfferRow $b) => $a->sortOrder <=> $b->sortOrder);

        return $rows;
    }
}
