<?php

declare(strict_types=1);

namespace App\Application\Dunning\Query;

use App\Application\Query\QueryInterface;

final class GetDunningNotice implements QueryInterface
{
    public function __construct(public readonly string $id)
    {
    }
}
