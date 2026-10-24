<?php

declare(strict_types=1);

namespace App\Application\Invoice\Query;

use App\Application\Query\QueryInterface;

final class GetInvoice implements QueryInterface
{
    public function __construct(public readonly string $id)
    {
    }
}
