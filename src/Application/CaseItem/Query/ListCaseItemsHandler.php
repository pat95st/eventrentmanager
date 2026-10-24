<?php

declare(strict_types=1);

namespace App\Application\CaseItem\Query;

use App\Application\Common\PaginatedResult;
use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\CaseItem\CaseItemRepositoryInterface;

final class ListCaseItemsHandler implements QueryHandlerInterface
{
    public function __construct(private readonly CaseItemRepositoryInterface $cases)
    {
    }

    public function handle(QueryInterface $query): PaginatedResult
    {
        assert($query instanceof ListCaseItems);

        $offset = PaginatedResult::offsetFor($query->page, $query->pageSize);
        $items = $this->cases->search($query->searchTerm, $query->manufacturerId, $query->pageSize, $offset);
        $totalCount = $this->cases->countSearch($query->searchTerm, $query->manufacturerId);

        return new PaginatedResult($items, $totalCount, $query->page, $query->pageSize);
    }
}
