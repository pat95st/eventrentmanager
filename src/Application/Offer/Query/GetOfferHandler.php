<?php

declare(strict_types=1);

namespace App\Application\Offer\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Article\ArticleRepositoryInterface;
use App\Domain\Customer\CustomerRepositoryInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\InventoryNumber\InventoryNumberRepositoryInterface;
use App\Domain\LaborTime\LaborTimeArticleRepositoryInterface;
use App\Domain\Offer\OfferRepositoryInterface;

final class GetOfferHandler implements QueryHandlerInterface
{
    public function __construct(
        private readonly OfferRepositoryInterface $offers,
        private readonly CustomerRepositoryInterface $customers,
        private readonly ArticleRepositoryInterface $articles,
        private readonly LaborTimeArticleRepositoryInterface $laborTimeArticles,
        private readonly InventoryNumberRepositoryInterface $inventoryNumbers,
    ) {
    }

    public function handle(QueryInterface $query): OfferDetail
    {
        assert($query instanceof GetOffer);

        $offer = $this->offers->findById($query->id);
        if ($offer === null) {
            throw new NotFoundException("Angebot \"{$query->id}\" wurde nicht gefunden.");
        }

        $customer = $offer->customerId !== null ? $this->customers->findById($offer->customerId) : null;

        $rawItems = $this->offers->findItemsByOffer($offer->id);
        $items = array_values(array_filter(array_map(
            function ($item) {
                $article = $this->articles->findById($item->articleId);

                return $article !== null ? new OfferItemWithArticle($item, $article) : null;
            },
            $rawItems
        )));

        $rawLaborItems = $this->offers->findLaborItemsByOffer($offer->id);
        $laborItems = array_values(array_filter(array_map(
            function ($item) {
                $laborTimeArticle = $this->laborTimeArticles->findById($item->laborTimeArticleId);

                return $laborTimeArticle !== null ? new OfferLaborItemWithArticle($item, $laborTimeArticle) : null;
            },
            $rawLaborItems
        )));

        $rawSaleItems = $this->offers->findSaleItemsByOffer($offer->id);
        $saleItems = array_values(array_filter(array_map(
            function ($item) {
                $article = $this->articles->findById($item->articleId);
                if ($article === null) {
                    return null;
                }

                $inventoryNumber = $item->inventoryNumberId !== null ? $this->inventoryNumbers->findById($item->inventoryNumberId) : null;

                return new OfferSaleItemWithArticle($item, $article, $inventoryNumber);
            },
            $rawSaleItems
        )));

        return new OfferDetail($offer, $customer, $items, $laborItems, $saleItems);
    }
}
