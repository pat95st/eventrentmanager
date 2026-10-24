<?php

declare(strict_types=1);

namespace App\Application\Offer\Query;

use App\Domain\Article\Article;
use App\Domain\Offer\OfferItem;

final class OfferItemWithArticle
{
    public function __construct(
        public readonly OfferItem $item,
        public readonly Article $article,
    ) {
    }

    public function lineTotal(): float
    {
        return $this->item->lineTotal();
    }
}
