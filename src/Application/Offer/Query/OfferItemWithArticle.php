<?php

declare(strict_types=1);

namespace App\Application\Offer\Query;

use App\Domain\Article\Article;
use App\Domain\Article\ArticleDisplayName;
use App\Domain\Manufacturer\Manufacturer;
use App\Domain\Offer\OfferItem;

final class OfferItemWithArticle
{
    public function __construct(
        public readonly OfferItem $item,
        public readonly Article $article,
        public readonly ?Manufacturer $manufacturer = null,
    ) {
    }

    public function lineTotal(): float
    {
        return $this->item->lineTotal();
    }

    // "Hersteller Artikelbezeichnung" (Nutzerwunsch: Hersteller immer vor
    // der Artikelbezeichnung) für Anzeige und Angebots-PDF.
    public function displayName(): string
    {
        return ArticleDisplayName::of($this->article, $this->manufacturer);
    }
}
