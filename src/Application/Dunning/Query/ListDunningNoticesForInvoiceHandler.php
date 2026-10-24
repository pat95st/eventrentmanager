<?php

declare(strict_types=1);

namespace App\Application\Dunning\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Dunning\DunningNoticeRepositoryInterface;

final class ListDunningNoticesForInvoiceHandler implements QueryHandlerInterface
{
    public function __construct(private readonly DunningNoticeRepositoryInterface $dunningNotices)
    {
    }

    public function handle(QueryInterface $query): array
    {
        assert($query instanceof ListDunningNoticesForInvoice);

        return $this->dunningNotices->findByInvoice($query->invoiceId);
    }
}
