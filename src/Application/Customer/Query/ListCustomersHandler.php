<?php

declare(strict_types=1);

namespace App\Application\Customer\Query;

use App\Application\Common\PaginatedResult;
use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Customer\CustomerRepositoryInterface;

final class ListCustomersHandler implements QueryHandlerInterface
{
    public function __construct(private readonly CustomerRepositoryInterface $customers)
    {
    }

    public function handle(QueryInterface $query): PaginatedResult
    {
        assert($query instanceof ListCustomers);

        $offset = $query->pageSize !== null ? PaginatedResult::offsetFor($query->page ?? 1, $query->pageSize) : null;

        $items = $this->customers->findAll($query->search, $query->pageSize, $offset);
        $totalCount = $this->customers->countAll($query->search);

        return new PaginatedResult($items, $totalCount, $query->page ?? 1, $query->pageSize ?? max($totalCount, 1));
    }
}
