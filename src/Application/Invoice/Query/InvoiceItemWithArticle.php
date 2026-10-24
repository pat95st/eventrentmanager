<?php

declare(strict_types=1);

namespace App\Application\Invoice\Query;

use App\Domain\Article\Article;
use App\Domain\Invoice\InvoiceItem;

final class InvoiceItemWithArticle
{
    public function __construct(
        public readonly InvoiceItem $item,
        public readonly Article $article,
    ) {
    }

    public function lineTotal(): float
    {
        return $this->item->lineTotal();
    }
}
