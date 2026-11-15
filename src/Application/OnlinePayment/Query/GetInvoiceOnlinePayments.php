<?php

declare(strict_types=1);

namespace App\Application\OnlinePayment\Query;

use App\Application\Query\QueryInterface;

/** Online payment attempts of one invoice (invoice detail page), newest first. */
final class GetInvoiceOnlinePayments implements QueryInterface
{
    public function __construct(public readonly string $invoiceId)
    {
    }
}
