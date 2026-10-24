<?php

declare(strict_types=1);

namespace App\Application\Invoice\Query;

use App\Domain\Invoice\InvoiceLaborItem;
use App\Domain\LaborTime\LaborTimeArticle;

final class InvoiceLaborItemWithArticle
{
    public function __construct(
        public readonly InvoiceLaborItem $item,
        public readonly LaborTimeArticle $laborTimeArticle,
    ) {
    }

    public function lineTotal(): float
    {
        return $this->item->lineTotal();
    }
}
