<?php

declare(strict_types=1);

namespace App\Application\Rental\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Article\ArticleAccessoryRepositoryInterface;
use App\Domain\Article\ArticlePreIssueCheckRepositoryInterface;
use App\Domain\Article\ArticleRepositoryInterface;
use App\Domain\Customer\CustomerRepositoryInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\InventoryNumber\InventoryNumberRepositoryInterface;
use App\Domain\Manufacturer\ManufacturerRepositoryInterface;
use App\Domain\Rental\RentalIssuedItemRepositoryInterface;
use App\Domain\Rental\RentalItemRepositoryInterface;
use App\Domain\Rental\RentalRepositoryInterface;

final class GetRentalHandler implements QueryHandlerInterface
{
    public function __construct(
        private readonly RentalRepositoryInterface $rentals,
        private readonly CustomerRepositoryInterface $customers,
        private readonly RentalItemRepositoryInterface $items,
        private readonly RentalIssuedItemRepositoryInterface $issuedItems,
        private readonly ArticleRepositoryInterface $articles,
        private readonly InventoryNumberRepositoryInterface $inventoryNumbers,
        private readonly ArticleAccessoryRepositoryInterface $accessories,
        private readonly ArticlePreIssueCheckRepositoryInterface $preIssueChecks,
        private readonly ManufacturerRepositoryInterface $manufacturers,
    ) {
    }

    public function handle(QueryInterface $query): RentalDetail
    {
        assert($query instanceof GetRental);

        $rental = $this->rentals->findById($query->id);
        if ($rental === null) {
            throw new NotFoundException("Vermietung \"{$query->id}\" wurde nicht gefunden.");
        }

        $customer = $this->customers->findById($rental->customerId);
        if ($customer === null) {
            throw new NotFoundException('Kunde der Vermietung wurde nicht gefunden.');
        }

        // Siehe GetInvoiceHandler für die identische Begründung des
        // kleinen Hersteller-Caches.
        $manufacturerCache = [];
        $manufacturerFor = function (string $manufacturerId) use (&$manufacturerCache) {
            if (!array_key_exists($manufacturerId, $manufacturerCache)) {
                $manufacturerCache[$manufacturerId] = $this->manufacturers->findById($manufacturerId);
            }

            return $manufacturerCache[$manufacturerId];
        };

        $rawItems = $this->items->findByRental($rental->id);
        $items = array_values(array_filter(array_map(
            function ($item) use ($rental, $manufacturerFor) {
                $article = $this->articles->findById($item->articleId);
                if ($article === null) {
                    return null;
                }

                $issuedCount = $this->issuedItems->countByRentalAndArticle($rental->id, $item->articleId);
                $accessories = $this->accessories->findByArticle($item->articleId);
                $preIssueChecks = $this->preIssueChecks->findByArticle($item->articleId);

                return new RentalItemWithArticle($item, $article, $issuedCount, $accessories, $preIssueChecks, $manufacturerFor(($article->manufacturerId ?? '')));
            },
            $rawItems
        )));

        $rawIssuedItems = $this->issuedItems->findByRental($rental->id);
        $issuedItems = array_values(array_filter(array_map(
            function ($issuedItem) use ($manufacturerFor) {
                $inventoryNumber = $this->inventoryNumbers->findById($issuedItem->inventoryNumberId);
                $article = $this->articles->findById($issuedItem->articleId);
                if ($inventoryNumber === null || $article === null) {
                    return null;
                }

                return new RentalIssuedItemWithDetails($issuedItem, $inventoryNumber, $article, $manufacturerFor(($article->manufacturerId ?? '')));
            },
            $rawIssuedItems
        )));

        return new RentalDetail($rental, $customer, $items, $issuedItems);
    }
}
