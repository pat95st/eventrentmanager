<?php

declare(strict_types=1);

namespace App\Application\Dunning\Query;

use App\Application\Query\QueryInterface;

final class ListDunningNoticesForInvoice implements QueryInterface
{
    public function __construct(public readonly string $invoiceId)
    {
    }
}
