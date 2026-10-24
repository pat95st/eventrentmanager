<?php

declare(strict_types=1);

namespace App\Application\OrderConfirmation\Query;

use App\Domain\Article\Article;
use App\Domain\OrderConfirmation\OrderConfirmationItem;

final class OrderConfirmationItemWithArticle
{
    public function __construct(
        public readonly OrderConfirmationItem $item,
        public readonly Article $article,
    ) {
    }

    public function lineTotal(): float
    {
        return $this->item->lineTotal();
    }
}
