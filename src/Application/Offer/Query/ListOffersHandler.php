<?php

declare(strict_types=1);

namespace App\Application\Offer\Query;

use App\Application\Common\PaginatedResult;
use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Customer\CustomerRepositoryInterface;
use App\Domain\Offer\OfferRepositoryInterface;

final class ListOffersHandler implements QueryHandlerInterface
{
    public function __construct(
        private readonly OfferRepositoryInterface $offers,
        private readonly CustomerRepositoryInterface $customers,
    ) {
    }

    public function handle(QueryInterface $query): PaginatedResult
    {
        assert($query instanceof ListOffers);

        $offset = PaginatedResult::offsetFor($query->page, $query->pageSize);
        $offers = $this->offers->search($query->searchTerm, $query->status, $query->pageSize, $offset);
        $totalCount = $this->offers->countSearch($query->searchTerm, $query->status);

        $entries = array_map(
            fn ($offer) => new OfferListEntry($offer, $offer->customerId !== null ? $this->customers->findById($offer->customerId) : null),
            $offers
        );

        return new PaginatedResult($entries, $totalCount, $query->page, $query->pageSize);
    }
}
