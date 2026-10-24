<?php

declare(strict_types=1);

namespace App\Application\Purchase\Query;

use App\Domain\Article\Article;
use App\Domain\Purchase\PurchaseOrderItem;

final class PurchaseOrderItemWithArticle
{
    public function __construct(
        public readonly PurchaseOrderItem $item,
        public readonly Article $article,
    ) {
    }
}
