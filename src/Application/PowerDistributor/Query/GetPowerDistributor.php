<?php

declare(strict_types=1);

namespace App\Application\PowerDistributor\Query;

use App\Application\Query\QueryInterface;

final class GetPowerDistributor implements QueryInterface
{
    public function __construct(public readonly string $articleId)
    {
    }
}
