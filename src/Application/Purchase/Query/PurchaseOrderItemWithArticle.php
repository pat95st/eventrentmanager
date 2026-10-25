<?php

declare(strict_types=1);

namespace App\Application\Purchase\Query;

use App\Domain\Article\Article;
use App\Domain\Article\ArticleDisplayName;
use App\Domain\Manufacturer\Manufacturer;
use App\Domain\Purchase\PurchaseOrderItem;

final class PurchaseOrderItemWithArticle
{
    public function __construct(
        public readonly PurchaseOrderItem $item,
        public readonly Article $article,
        public readonly ?Manufacturer $manufacturer = null,
    ) {
    }

    // "Hersteller Artikelbezeichnung" (Nutzerwunsch: Hersteller immer vor
    // der Artikelbezeichnung) für Anzeige in der Bestellansicht.
    public function displayName(): string
    {
        return ArticleDisplayName::of($this->article, $this->manufacturer);
    }
}
