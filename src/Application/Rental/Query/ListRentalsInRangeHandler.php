<?php

declare(strict_types=1);

namespace App\Application\Rental\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Article\ArticleRepositoryInterface;
use App\Domain\Customer\CustomerRepositoryInterface;
use App\Domain\InventoryNumber\InventoryNumberRepositoryInterface;
use App\Domain\Rental\RentalIssuedItemRepositoryInterface;
use App\Domain\Rental\RentalItemRepositoryInterface;
use App\Domain\Rental\RentalRepositoryInterface;
use DateTimeImmutable;

final class ListRentalsInRangeHandler implements QueryHandlerInterface
{
    public function __construct(
        private readonly RentalRepositoryInterface $rentals,
        private readonly CustomerRepositoryInterface $customers,
        private readonly RentalItemRepositoryInterface $items,
        private readonly RentalIssuedItemRepositoryInterface $issuedItems,
        private readonly ArticleRepositoryInterface $articles,
        private readonly InventoryNumberRepositoryInterface $inventoryNumbers,
    ) {
    }

    public function handle(QueryInterface $query): array
    {
        assert($query instanceof ListRentalsInRange);

        $rentals = $this->rentals->findOverlapping(new DateTimeImmutable($query->from), new DateTimeImmutable($query->to));

        return array_values(array_filter(array_map(
            function ($rental) {
                $customer = $this->customers->findById($rental->customerId);
                if ($customer === null) {
                    return null;
                }

                $issuedItems = $this->issuedItems->findByRental($rental->id);
                if ($issuedItems !== []) {
                    $labels = array_values(array_filter(array_map(
                        function ($issuedItem) {
                            $inventoryNumber = $this->inventoryNumbers->findById($issuedItem->inventoryNumberId);

                            return $inventoryNumber?->number;
                        },
                        $issuedItems
                    )));
                } else {
                    $rentalItems = $this->items->findByRental($rental->id);
                    $labels = array_values(array_filter(array_map(
                        function ($item) {
                            $article = $this->articles->findById($item->articleId);

                            return $article !== null ? $article->name . ' (' . $item->quantity . 'x)' : null;
                        },
                        $rentalItems
                    )));
                }

                return new CalendarRentalEntry($rental, $customer, $labels);
            },
            $rentals
        )));
    }
}
