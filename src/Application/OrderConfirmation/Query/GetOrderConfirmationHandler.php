<?php

declare(strict_types=1);

namespace App\Application\OrderConfirmation\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Article\ArticleRepositoryInterface;
use App\Domain\Customer\CustomerContactRepositoryInterface;
use App\Domain\Customer\CustomerRepositoryInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Manufacturer\ManufacturerRepositoryInterface;
use App\Domain\OrderConfirmation\OrderConfirmationRepositoryInterface;

final class GetOrderConfirmationHandler implements QueryHandlerInterface
{
    public function __construct(
        private readonly OrderConfirmationRepositoryInterface $orderConfirmations,
        private readonly CustomerRepositoryInterface $customers,
        private readonly ArticleRepositoryInterface $articles,
        private readonly ManufacturerRepositoryInterface $manufacturers,
        private readonly CustomerContactRepositoryInterface $customerContacts,
    ) {
    }

    public function handle(QueryInterface $query): OrderConfirmationDetail
    {
        assert($query instanceof GetOrderConfirmation);

        $orderConfirmation = $this->orderConfirmations->findById($query->id);
        if ($orderConfirmation === null) {
            throw new NotFoundException("Auftragsbestätigung \"{$query->id}\" wurde nicht gefunden.");
        }

        $customer = $orderConfirmation->customerId !== null ? $this->customers->findById($orderConfirmation->customerId) : null;
        $contact = $orderConfirmation->contactId !== null ? $this->customerContacts->findById($orderConfirmation->contactId) : null;

        // Siehe GetInvoiceHandler für die identische Begründung des
        // kleinen Hersteller-Caches.
        $manufacturerCache = [];
        $manufacturerFor = function (string $manufacturerId) use (&$manufacturerCache) {
            if (!array_key_exists($manufacturerId, $manufacturerCache)) {
                $manufacturerCache[$manufacturerId] = $this->manufacturers->findById($manufacturerId);
            }

            return $manufacturerCache[$manufacturerId];
        };

        $rawItems = $this->orderConfirmations->findItemsByOrderConfirmation($orderConfirmation->id);
        $items = array_values(array_filter(array_map(
            function ($item) use ($manufacturerFor) {
                $article = $this->articles->findById($item->articleId);

                return $article !== null ? new OrderConfirmationItemWithArticle($item, $article, $manufacturerFor($article->manufacturerId)) : null;
            },
            $rawItems
        )));

        $freeTextItems = $this->orderConfirmations->findFreeTextItemsByOrderConfirmation($orderConfirmation->id);
        $sectionTexts = $this->orderConfirmations->findSectionTextsByOrderConfirmation($orderConfirmation->id);

        return new OrderConfirmationDetail($orderConfirmation, $customer, $items, $freeTextItems, $sectionTexts, $contact);
    }
}
