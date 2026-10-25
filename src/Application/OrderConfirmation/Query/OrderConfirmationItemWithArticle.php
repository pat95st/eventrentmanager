<?php

declare(strict_types=1);

namespace App\Application\OrderConfirmation\Query;

use App\Domain\Article\Article;
use App\Domain\Article\ArticleDisplayName;
use App\Domain\Manufacturer\Manufacturer;
use App\Domain\OrderConfirmation\OrderConfirmationItem;

final class OrderConfirmationItemWithArticle
{
    public function __construct(
        public readonly OrderConfirmationItem $item,
        public readonly Article $article,
        public readonly ?Manufacturer $manufacturer = null,
    ) {
    }

    public function lineTotal(): float
    {
        return $this->item->lineTotal();
    }

    // "Hersteller Artikelbezeichnung" (Nutzerwunsch: Hersteller immer vor
    // der Artikelbezeichnung) für Anzeige und PDF.
    public function displayName(): string
    {
        return ArticleDisplayName::of($this->article, $this->manufacturer);
    }
}
