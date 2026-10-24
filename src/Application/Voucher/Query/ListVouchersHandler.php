<?php

declare(strict_types=1);

namespace App\Application\Voucher\Query;

use App\Application\Common\PaginatedResult;
use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Voucher\VoucherRepositoryInterface;

final class ListVouchersHandler implements QueryHandlerInterface
{
    public function __construct(private readonly VoucherRepositoryInterface $vouchers)
    {
    }

    public function handle(QueryInterface $query): PaginatedResult
    {
        assert($query instanceof ListVouchers);

        $offset = $query->pageSize !== null ? PaginatedResult::offsetFor($query->page ?? 1, $query->pageSize) : null;

        $items = $this->vouchers->findAll($query->search, $query->pageSize, $offset);
        $totalCount = $this->vouchers->countAll($query->search);

        return new PaginatedResult($items, $totalCount, $query->page ?? 1, $query->pageSize ?? max($totalCount, 1));
    }
}
