<?php

declare(strict_types=1);

namespace App\Application\Rental\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Customer\CustomerRepositoryInterface;
use App\Domain\Rental\RentalRepositoryInterface;

/**
 * Alle Veranstaltungen, die einem gespeicherten Veranstaltungsort zugeordnet
 * sind, für die Veranstaltungsort-Detailseite — dort nach vergangen/kommend
 * aufgeteilt angezeigt (siehe VenueController).
 */
final class ListRentalsForVenueHandler implements QueryHandlerInterface
{
    public function __construct(
        private readonly RentalRepositoryInterface $rentals,
        private readonly CustomerRepositoryInterface $customers,
    ) {
    }

    public function handle(QueryInterface $query): array
    {
        assert($query instanceof ListRentalsForVenue);

        $rentals = $this->rentals->findByVenue($query->venueId);

        return array_values(array_filter(array_map(
            function ($rental) {
                $customer = $this->customers->findById($rental->customerId);
                if ($customer === null) {
                    return null;
                }

                return new VenueRentalEntry($rental, $customer);
            },
            $rentals
        )));
    }
}
