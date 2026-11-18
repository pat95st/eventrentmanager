<?php

declare(strict_types=1);

namespace App\Application\Routing\Query;

use App\Application\Query\QueryInterface;

final class GetTourRoute implements QueryInterface
{
    public function __construct(public readonly string $rentalId)
    {
    }
}
