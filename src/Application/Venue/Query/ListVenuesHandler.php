<?php

declare(strict_types=1);

namespace App\Application\Venue\Query;

use App\Application\Common\PaginatedResult;
use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Venue\VenueRepositoryInterface;

final class ListVenuesHandler implements QueryHandlerInterface
{
    public function __construct(private readonly VenueRepositoryInterface $venues)
    {
    }

    public function handle(QueryInterface $query): PaginatedResult
    {
        assert($query instanceof ListVenues);

        $offset = PaginatedResult::offsetFor($query->page, $query->pageSize);
        $items = $this->venues->findAll($query->search, $query->pageSize, $offset);
        $totalCount = $this->venues->countAll($query->search);

        return new PaginatedResult($items, $totalCount, $query->page, $query->pageSize);
    }
}
