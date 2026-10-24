<?php

declare(strict_types=1);

namespace App\Application\Receipt\Query;

use App\Application\Query\QueryInterface;

final class GetReceipt implements QueryInterface
{
    public function __construct(public readonly string $id)
    {
    }
}
