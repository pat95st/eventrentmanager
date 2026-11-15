<?php

declare(strict_types=1);

namespace App\Application\OnlinePayment\Query;

use App\Application\Query\QueryInterface;

/** Data of the public payment page /zahlung/{token}. */
final class GetInvoicePaymentPage implements QueryInterface
{
    public function __construct(public readonly string $token)
    {
    }
}
