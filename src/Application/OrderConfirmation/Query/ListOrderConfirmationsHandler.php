<?php

declare(strict_types=1);

namespace App\Application\OrderConfirmation\Query;

use App\Application\Common\PaginatedResult;
use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Customer\CustomerRepositoryInterface;
use App\Domain\OrderConfirmation\OrderConfirmationRepositoryInterface;

final class ListOrderConfirmationsHandler implements QueryHandlerInterface
{
    public function __construct(
        private readonly OrderConfirmationRepositoryInterface $orderConfirmations,
        private readonly CustomerRepositoryInterface $customers,
    ) {
    }

    public function handle(QueryInterface $query): PaginatedResult
    {
        assert($query instanceof ListOrderConfirmations);

        $offset = PaginatedResult::offsetFor($query->page, $query->pageSize);
        $orderConfirmations = $this->orderConfirmations->search($query->searchTerm, $query->status, $query->pageSize, $offset);
        $totalCount = $this->orderConfirmations->countSearch($query->searchTerm, $query->status);

        $entries = array_map(
            fn ($oc) => new OrderConfirmationListEntry($oc, $oc->customerId !== null ? $this->customers->findById($oc->customerId) : null),
            $orderConfirmations
        );

        return new PaginatedResult($entries, $totalCount, $query->page, $query->pageSize);
    }
}
