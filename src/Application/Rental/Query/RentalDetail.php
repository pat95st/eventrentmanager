<?php

declare(strict_types=1);

namespace App\Application\Rental\Query;

use App\Domain\Customer\Customer;
use App\Domain\Rental\Rental;

final class RentalDetail
{
    /**
     * @param RentalItemWithArticle[] $items
     * @param RentalIssuedItemWithDetails[] $issuedItems
     */
    public function __construct(
        public readonly Rental $rental,
        public readonly Customer $customer,
        public readonly array $items,
        public readonly array $issuedItems,
    ) {
    }
}
