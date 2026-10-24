<?php

declare(strict_types=1);

namespace App\Application\Rental\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Customer\CustomerRepositoryInterface;
use App\Domain\Rental\RentalIssuedItemRepositoryInterface;
use App\Domain\Rental\RentalItemRepositoryInterface;
use App\Domain\Rental\RentalRepositoryInterface;

/**
 * Alle Vermietungen, in denen dieser Artikel eingeplant ist (über alle
 * Vermietungen hinweg), für die Artikel-Detailseite — dort nach vergangen/
 * kommend aufgeteilt angezeigt (siehe ArticleController).
 */
final class ListRentalsForArticleHandler implements QueryHandlerInterface
{
    public function __construct(
        private readonly RentalItemRepositoryInterface $items,
        private readonly RentalRepositoryInterface $rentals,
        private readonly CustomerRepositoryInterface $customers,
        private readonly RentalIssuedItemRepositoryInterface $issuedItems,
    ) {
    }

    public function handle(QueryInterface $query): array
    {
        assert($query instanceof ListRentalsForArticle);

        $items = $this->items->findByArticle($query->articleId);

        return array_values(array_filter(array_map(
            function ($item) use ($query) {
                $rental = $this->rentals->findById($item->rentalId);
                if ($rental === null) {
                    return null;
                }

                $customer = $this->customers->findById($rental->customerId);
                if ($customer === null) {
                    return null;
                }

                $issuedCount = $this->issuedItems->countByRentalAndArticle($item->rentalId, $query->articleId);

                return new ArticleRentalEntry($rental, $customer, $item, $issuedCount);
            },
            $items
        )));
    }
}
