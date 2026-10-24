<?php

declare(strict_types=1);

namespace App\Application\Rental\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Customer\CustomerRepositoryInterface;
use App\Domain\Rental\RentalIssuedItemRepositoryInterface;
use App\Domain\Rental\RentalRepositoryInterface;

/**
 * Vermietungs-Historie einer einzelnen Inventurnummer (welche Vermietungen
 * genau dieses physische Exemplar bereits eingesetzt haben), für die
 * Inventurnummer-Detailseite.
 */
final class ListRentalHistoryForInventoryNumberHandler implements QueryHandlerInterface
{
    public function __construct(
        private readonly RentalIssuedItemRepositoryInterface $issuedItems,
        private readonly RentalRepositoryInterface $rentals,
        private readonly CustomerRepositoryInterface $customers,
    ) {
    }

    public function handle(QueryInterface $query): array
    {
        assert($query instanceof ListRentalHistoryForInventoryNumber);

        $issuedItems = $this->issuedItems->findByInventoryNumber($query->inventoryNumberId);

        return array_values(array_filter(array_map(
            function ($issuedItem) {
                $rental = $this->rentals->findById($issuedItem->rentalId);
                if ($rental === null) {
                    return null;
                }

                $customer = $this->customers->findById($rental->customerId);
                if ($customer === null) {
                    return null;
                }

                return new RentalHistoryEntry($rental, $customer, $issuedItem->issuedAt);
            },
            $issuedItems
        )));
    }
}
