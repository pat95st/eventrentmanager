<?php

declare(strict_types=1);

namespace App\Application\Supplier\Query;

use App\Application\Query\QueryInterface;

final class GetSupplier implements QueryInterface
{
    public function __construct(public readonly string $id)
    {
    }
}
