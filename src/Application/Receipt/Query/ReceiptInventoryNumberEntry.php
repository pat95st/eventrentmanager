<?php

declare(strict_types=1);

namespace App\Application\Receipt\Query;

use App\Domain\Article\Article;
use App\Domain\InventoryNumber\InventoryNumber;

final class ReceiptInventoryNumberEntry
{
    public function __construct(
        public readonly InventoryNumber $inventoryNumber,
        public readonly ?Article $article,
    ) {
    }
}
