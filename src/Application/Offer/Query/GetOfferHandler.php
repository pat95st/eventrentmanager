<?php

declare(strict_types=1);

namespace App\Application\Offer\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Article\ArticleRepositoryInterface;
use App\Domain\Customer\CustomerContactRepositoryInterface;
use App\Application\Settings\PaymentSelection;
use App\Domain\Settings\InvoiceSettingsRepositoryInterface;
use App\Application\Article\Query\BundleComponentResolver;
use App\Domain\Settings\PaymentMethodRepositoryInterface;
use App\Domain\Customer\CustomerRepositoryInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\InventoryNumber\InventoryNumberRepositoryInterface;
use App\Domain\LaborTime\LaborTimeArticleRepositoryInterface;
use App\Domain\Manufacturer\ManufacturerRepositoryInterface;
use App\Domain\Offer\OfferRepositoryInterface;
use App\Domain\User\UserRepositoryInterface;

final class GetOfferHandler implements QueryHandlerInterface
{
    public function __construct(
        private readonly OfferRepositoryInterface $offers,
        private readonly CustomerRepositoryInterface $customers,
        private readonly ArticleRepositoryInterface $articles,
        private readonly LaborTimeArticleRepositoryInterface $laborTimeArticles,
        private readonly InventoryNumberRepositoryInterface $inventoryNumbers,
        private readonly ManufacturerRepositoryInterface $manufacturers,
        private readonly UserRepositoryInterface $users,
        private readonly CustomerContactRepositoryInterface $customerContacts,
        private readonly ?PaymentMethodRepositoryInterface $paymentMethods = null,
        private readonly ?BundleComponentResolver $bundleResolver = null,
        private readonly ?InvoiceSettingsRepositoryInterface $invoiceSettings = null,
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
        $contact = $offer->contactId !== null ? $this->customerContacts->findById($offer->contactId) : null;
        $paymentMethod = PaymentSelection::resolvePaymentMethod($this->paymentMethods, $offer->paymentMethodId);

        // Siehe GetInvoiceHandler für die identische Begründung des
        // kleinen Herstellen-Caches (mehrfach vorkommende Artikel in
        // einem Angebot lösen so nicht wiederholt dieselbe Abfrage aus).
        $manufacturerCache = [];
        $manufacturerFor = function (string $manufacturerId) use (&$manufacturerCache) {
            if (!array_key_exists($manufacturerId, $manufacturerCache)) {
                $manufacturerCache[$manufacturerId] = $this->manufacturers->findById($manufacturerId);
            }

            return $manufacturerCache[$manufacturerId];
        };

        $rawItems = $this->offers->findItemsByOffer($offer->id);
        $items = array_values(array_filter(array_map(
            function ($item) use ($manufacturerFor) {
                $article = $this->articles->findById($item->articleId);

                return $article !== null ? new OfferItemWithArticle($item, $article, $manufacturerFor($article->manufacturerId)) : null;
            },
            $rawItems
        )));

        // Mitarbeiter je Arbeitszeit-Position wird ebenfalls über einen
        // kleinen Cache aufgelöst (siehe GetInvoiceHandler für die
        // identische Begründung).
        $userCache = [];
        $userFor = function (?string $userId) use (&$userCache) {
            if ($userId === null) {
                return null;
            }
            if (!array_key_exists($userId, $userCache)) {
                $userCache[$userId] = $this->users->findById($userId);
            }

            return $userCache[$userId];
        };

        $rawLaborItems = $this->offers->findLaborItemsByOffer($offer->id);
        $laborItems = array_values(array_filter(array_map(
            function ($item) use ($userFor) {
                $laborTimeArticle = $this->laborTimeArticles->findById($item->laborTimeArticleId);

                return $laborTimeArticle !== null
                    ? new OfferLaborItemWithArticle($item, $laborTimeArticle, $userFor($item->userId))
                    : null;
            },
            $rawLaborItems
        )));

        $rawSaleItems = $this->offers->findSaleItemsByOffer($offer->id);
        $saleItems = array_values(array_filter(array_map(
            function ($item) use ($manufacturerFor) {
                $article = $this->articles->findById($item->articleId);
                if ($article === null) {
                    return null;
                }

                $inventoryNumber = $item->inventoryNumberId !== null ? $this->inventoryNumbers->findById($item->inventoryNumberId) : null;

                return new OfferSaleItemWithArticle($item, $article, $inventoryNumber, $manufacturerFor($article->manufacturerId));
            },
            $rawSaleItems
        )));

        $freeTextItems = $this->offers->findFreeTextItemsByOffer($offer->id);
        $sectionTexts = $this->offers->findSectionTextsByOffer($offer->id);

        $taxSettings = $this->invoiceSettings?->get();
        $bundleComponents = $this->bundleResolver?->resolve(array_map(static fn ($entry) => $entry->article, $items)) ?? [];
        return new OfferDetail($offer, $customer, $items, $laborItems, $saleItems, $freeTextItems, $sectionTexts, $contact, $paymentMethod, bundleComponents: $bundleComponents, isSmallBusiness: $taxSettings?->isSmallBusiness, vatRatePercent: $taxSettings?->vatRatePercent);
    }
}
