<?php

declare(strict_types=1);

namespace App\Application\Federation\Query;

use App\Application\Query\QueryInterface;

/** Partner requests linked to an event plus the active partners. */
final class ListPartnerRequestsForRental implements QueryInterface
{
    public function __construct(
        public readonly string $rentalId,
    ) {
    }
}
