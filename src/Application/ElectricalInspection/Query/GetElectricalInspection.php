<?php

declare(strict_types=1);

namespace App\Application\ElectricalInspection\Query;

use App\Application\Query\QueryInterface;

final class GetElectricalInspection implements QueryInterface
{
    public function __construct(public readonly string $id)
    {
    }
}
