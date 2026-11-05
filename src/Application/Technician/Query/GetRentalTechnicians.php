<?php

declare(strict_types=1);

namespace App\Application\Technician\Query;

use App\Application\Query\QueryInterface;

final class GetRentalTechnicians implements QueryInterface
{
    public function __construct(public readonly string $rentalId)
    {
    }
}
