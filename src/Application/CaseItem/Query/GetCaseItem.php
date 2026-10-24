<?php

declare(strict_types=1);

namespace App\Application\CaseItem\Query;

use App\Application\Query\QueryInterface;

final class GetCaseItem implements QueryInterface
{
    public function __construct(public readonly string $id)
    {
    }
}
