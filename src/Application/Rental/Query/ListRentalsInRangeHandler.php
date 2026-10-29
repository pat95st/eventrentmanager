<?php

declare(strict_types=1);

namespace App\Application\Rental\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Article\ArticleDisplayName;
use App\Domain\Article\ArticleRepositoryInterface;
use App\Domain\Customer\CustomerRepositoryInterface;
use App\Domain\InventoryNumber\InventoryNumberRepositoryInterface;
use App\Domain\Manufacturer\ManufacturerRepositoryInterface;
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
        private readonly ManufacturerRepositoryInterface $manufacturers,
    ) {
    }

    public function handle(QueryInterface $query): array
    {
        assert($query instanceof ListRentalsInRange);

        $rentals = $this->rentals->findOverlapping(new DateTimeImmutable($query->from), new DateTimeImmutable($query->to));

        // Siehe GetInvoiceHandler für die identische Begründung des
        // kleinen Hersteller-Caches.
        $manufacturerCache = [];

        return array_values(array_filter(array_map(
            function ($rental) use (&$manufacturerCache) {
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
                        function ($item) use (&$manufacturerCache) {
                            $article = $this->articles->findById($item->articleId);
                            if ($article === null) {
                                return null;
                            }

                            if (!array_key_exists(($article->manufacturerId ?? ''), $manufacturerCache)) {
                                $manufacturerCache[($article->manufacturerId ?? '')] = $this->manufacturers->findById(($article->manufacturerId ?? ''));
                            }

                            return ArticleDisplayName::of($article, $manufacturerCache[($article->manufacturerId ?? '')]) . ' (' . $item->quantity . 'x)';
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
