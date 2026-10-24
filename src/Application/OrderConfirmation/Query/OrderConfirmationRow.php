<?php

declare(strict_types=1);

namespace App\Application\OrderConfirmation\Query;

use App\Domain\OrderConfirmation\OrderConfirmationFreeTextItem;
use App\Domain\OrderConfirmation\OrderConfirmationSectionText;

/**
 * Eine Zeile des orderedRows()-Konzepts (siehe OrderConfirmationDetail):
 * reguläre Mietposition, Freitext-Posten oder reine Zwischenüberschrift,
 * gemeinsam nach $sortOrder sortiert — für Anzeige (show.php) und PDF
 * (OrderConfirmationPdfGenerator) in EINER Schleife, statt drei getrennten
 * Blöcken wie bisher bei Offer/Invoice.
 */
final class OrderConfirmationRow
{
    public const TYPE_ITEM = 'item';
    public const TYPE_FREE_TEXT = 'free_text';
    public const TYPE_SECTION = 'section';

    private function __construct(
        public readonly string $type,
        public readonly int $sortOrder,
        public readonly OrderConfirmationItemWithArticle|OrderConfirmationFreeTextItem|OrderConfirmationSectionText $data,
    ) {
    }

    public static function forItem(OrderConfirmationItemWithArticle $item): self
    {
        return new self(self::TYPE_ITEM, $item->item->sortOrder, $item);
    }

    public static function forFreeTextItem(OrderConfirmationFreeTextItem $item): self
    {
        return new self(self::TYPE_FREE_TEXT, $item->sortOrder, $item);
    }

    public static function forSectionText(OrderConfirmationSectionText $sectionText): self
    {
        return new self(self::TYPE_SECTION, $sectionText->sortOrder, $sectionText);
    }
}
