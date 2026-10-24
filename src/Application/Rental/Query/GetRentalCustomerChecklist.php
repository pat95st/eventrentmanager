<?php

declare(strict_types=1);

namespace App\Application\Rental\Query;

use App\Application\Query\QueryInterface;

final class GetRentalCustomerChecklist implements QueryInterface
{
    public function __construct(public readonly string $token)
    {
    }
}
