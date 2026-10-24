<?php

declare(strict_types=1);

namespace App\Application\Invoice\Query;

use App\Domain\Invoice\InvoiceFreeTextItem;
use App\Domain\Invoice\InvoiceSectionText;

/**
 * Eine Zeile des orderedRows()-Konzepts (siehe InvoiceDetail) — strukturell
 * identisch zu App\Application\Offer\Query\OfferRow, siehe dort für die
 * ausführliche Begründung.
 */
final class InvoiceRow
{
    public const TYPE_ITEM = 'item';
    public const TYPE_FREE_TEXT = 'free_text';
    public const TYPE_SECTION = 'section';

    private function __construct(
        public readonly string $type,
        public readonly int $sortOrder,
        public readonly InvoiceItemWithArticle|InvoiceFreeTextItem|InvoiceSectionText $data,
    ) {
    }

    public static function forItem(InvoiceItemWithArticle $item): self
    {
        return new self(self::TYPE_ITEM, $item->item->sortOrder, $item);
    }

    public static function forFreeTextItem(InvoiceFreeTextItem $item): self
    {
        return new self(self::TYPE_FREE_TEXT, $item->sortOrder, $item);
    }

    public static function forSectionText(InvoiceSectionText $sectionText): self
    {
        return new self(self::TYPE_SECTION, $sectionText->sortOrder, $sectionText);
    }
}
