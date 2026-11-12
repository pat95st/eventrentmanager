<?php

declare(strict_types=1);

namespace App\Application\Datev\Query;

use App\Application\Query\QueryInterface;

final class ListDatevExports implements QueryInterface
{
    public function __construct(public readonly int $limit = 10)
    {
    }
}
