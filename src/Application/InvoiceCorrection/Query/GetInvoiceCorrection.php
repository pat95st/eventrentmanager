<?php

declare(strict_types=1);

namespace App\Application\InvoiceCorrection\Query;

use App\Application\Query\QueryInterface;

final class GetInvoiceCorrection implements QueryInterface
{
    public function __construct(public readonly string $id)
    {
    }
}
