<?php

declare(strict_types=1);

namespace App\Application\Rental\Query;

use App\Application\Common\PaginatedResult;
use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Customer\CustomerRepositoryInterface;
use App\Domain\Rental\RentalRepositoryInterface;

final class ListRentalsHandler implements QueryHandlerInterface
{
    public function __construct(
        private readonly RentalRepositoryInterface $rentals,
        private readonly CustomerRepositoryInterface $customers,
    ) {
    }

    public function handle(QueryInterface $query): PaginatedResult
    {
        assert($query instanceof ListRentals);

        $offset = PaginatedResult::offsetFor($query->page, $query->pageSize);
        $rentals = $this->rentals->search($query->searchTerm, $query->customerId, $query->pageSize, $offset);
        $totalCount = $this->rentals->countSearch($query->searchTerm, $query->customerId);

        $entries = array_values(array_filter(array_map(
            function ($rental) {
                $customer = $this->customers->findById($rental->customerId);

                // Kunde könnte theoretisch parallel gelöscht worden sein; dann
                // blenden wir die Zeile aus statt zu crashen (analog zu Cases).
                return $customer !== null ? new RentalListEntry($rental, $customer) : null;
            },
            $rentals
        )));

        return new PaginatedResult($entries, $totalCount, $query->page, $query->pageSize);
    }
}
