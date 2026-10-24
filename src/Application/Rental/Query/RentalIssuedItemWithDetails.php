<?php

declare(strict_types=1);

namespace App\Application\Rental\Query;

use App\Domain\Article\Article;
use App\Domain\InventoryNumber\InventoryNumber;
use App\Domain\Rental\RentalIssuedItem;

final class RentalIssuedItemWithDetails
{
    public function __construct(
        public readonly RentalIssuedItem $issuedItem,
        public readonly InventoryNumber $inventoryNumber,
        public readonly Article $article,
    ) {
    }
}
