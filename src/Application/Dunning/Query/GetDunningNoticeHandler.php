<?php

declare(strict_types=1);

namespace App\Application\Dunning\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Customer\CustomerRepositoryInterface;
use App\Domain\Dunning\DunningNoticeRepositoryInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Invoice\InvoiceRepositoryInterface;

final class GetDunningNoticeHandler implements QueryHandlerInterface
{
    public function __construct(
        private readonly DunningNoticeRepositoryInterface $dunningNotices,
        private readonly InvoiceRepositoryInterface $invoices,
        private readonly CustomerRepositoryInterface $customers,
    ) {
    }

    public function handle(QueryInterface $query): DunningNoticeDetail
    {
        assert($query instanceof GetDunningNotice);

        $notice = $this->dunningNotices->findById($query->id);
        if ($notice === null) {
            throw new NotFoundException("Mahnung \"{$query->id}\" wurde nicht gefunden.");
        }

        $invoice = $this->invoices->findById($notice->invoiceId);
        if ($invoice === null) {
            throw new NotFoundException("Zur Mahnung \"{$query->id}\" gehörende Rechnung wurde nicht gefunden.");
        }

        $customer = $invoice->customerId !== null ? $this->customers->findById($invoice->customerId) : null;

        return new DunningNoticeDetail($notice, $invoice, $customer);
    }
}
