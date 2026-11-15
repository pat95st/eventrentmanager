<?php

declare(strict_types=1);

namespace App\Application\PowerPlan\Query;

use App\Application\Query\QueryInterface;

/** Power plan of an event with loads per phase and re-plugging suggestions (migration 159). */
final class GetRentalPowerPlan implements QueryInterface
{
    public function __construct(public readonly string $rentalId)
    {
    }
}
