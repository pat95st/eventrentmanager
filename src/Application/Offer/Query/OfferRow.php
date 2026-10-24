<?php

declare(strict_types=1);

namespace App\Application\Offer\Query;

use App\Domain\Offer\OfferFreeTextItem;
use App\Domain\Offer\OfferSectionText;

/**
 * Eine Zeile des orderedRows()-Konzepts (siehe OfferDetail): reguläre
 * Mietposition, Freitext-Posten oder reine Zwischenüberschrift, gemeinsam
 * nach $sortOrder sortiert — für Anzeige (offers/show.php) und PDF
 * (OfferPdfGenerator) in EINER Schleife. Arbeitszeit-/Verkaufspositionen
 * (OfferLaborItemWithArticle/OfferSaleItemWithArticle) bleiben davon bewusst
 * unberührt und erscheinen weiterhin in eigenen, festen Abschnitten (siehe
 * OfferDetail-Klassendoc) — zuerst für App\Application\OrderConfirmation
 * eingeführt (siehe dortiges OrderConfirmationRow) und hier übertragen.
 */
final class OfferRow
{
    public const TYPE_ITEM = 'item';
    public const TYPE_FREE_TEXT = 'free_text';
    public const TYPE_SECTION = 'section';

    private function __construct(
        public readonly string $type,
        public readonly int $sortOrder,
        public readonly OfferItemWithArticle|OfferFreeTextItem|OfferSectionText $data,
    ) {
    }

    public static function forItem(OfferItemWithArticle $item): self
    {
        return new self(self::TYPE_ITEM, $item->item->sortOrder, $item);
    }

    public static function forFreeTextItem(OfferFreeTextItem $item): self
    {
        return new self(self::TYPE_FREE_TEXT, $item->sortOrder, $item);
    }

    public static function forSectionText(OfferSectionText $sectionText): self
    {
        return new self(self::TYPE_SECTION, $sectionText->sortOrder, $sectionText);
    }
}
