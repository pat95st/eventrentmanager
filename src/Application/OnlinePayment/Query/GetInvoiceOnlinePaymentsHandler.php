<?php

declare(strict_types=1);

namespace App\Application\OnlinePayment\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\OnlinePayment\OnlinePayment;
use App\Domain\OnlinePayment\OnlinePaymentRepositoryInterface;

final class GetInvoiceOnlinePaymentsHandler implements QueryHandlerInterface
{
    public function __construct(private readonly OnlinePaymentRepositoryInterface $payments)
    {
    }

    /** @return OnlinePayment[] */
    public function handle(QueryInterface $query): array
    {
        assert($query instanceof GetInvoiceOnlinePayments);

        return $this->payments->findByInvoice($query->invoiceId);
    }
}
