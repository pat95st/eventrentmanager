<?php

declare(strict_types=1);

namespace App\Application\Offer\Query;

use App\Domain\Article\Article;
use App\Domain\InventoryNumber\InventoryNumber;
use App\Domain\Offer\OfferSaleItem;

final class OfferSaleItemWithArticle
{
    public function __construct(
        public readonly OfferSaleItem $item,
        public readonly Article $article,
        public readonly ?InventoryNumber $inventoryNumber,
    ) {
    }

    public function lineTotal(): float
    {
        return $this->item->lineTotal();
    }
}
