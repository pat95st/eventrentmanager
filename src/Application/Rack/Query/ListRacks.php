<?php

declare(strict_types=1);

namespace App\Application\Rack\Query;

use App\Application\Query\QueryInterface;

final class ListRacks implements QueryInterface
{
    public function __construct(public readonly ?string $search = null)
    {
    }
}
