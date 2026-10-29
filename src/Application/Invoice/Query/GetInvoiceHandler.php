<?php

declare(strict_types=1);

namespace App\Application\Invoice\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Article\ArticleRepositoryInterface;
use App\Domain\Customer\CustomerContactRepositoryInterface;
use App\Application\Settings\PaymentSelection;
use App\Application\Article\Query\BundleComponentResolver;
use App\Domain\Settings\BankAccountRepositoryInterface;
use App\Domain\Settings\PaymentMethodRepositoryInterface;
use App\Domain\Customer\CustomerRepositoryInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Invoice\InvoiceRepositoryInterface;
use App\Domain\LaborTime\LaborTimeArticleRepositoryInterface;
use App\Domain\Manufacturer\ManufacturerRepositoryInterface;
use App\Domain\User\UserRepositoryInterface;

final class GetInvoiceHandler implements QueryHandlerInterface
{
    public function __construct(
        private readonly InvoiceRepositoryInterface $invoices,
        private readonly CustomerRepositoryInterface $customers,
        private readonly ArticleRepositoryInterface $articles,
        private readonly LaborTimeArticleRepositoryInterface $laborTimeArticles,
        private readonly ManufacturerRepositoryInterface $manufacturers,
        private readonly UserRepositoryInterface $users,
        private readonly CustomerContactRepositoryInterface $customerContacts,
        private readonly ?PaymentMethodRepositoryInterface $paymentMethods = null,
        private readonly ?BankAccountRepositoryInterface $bankAccounts = null,
        private readonly ?BundleComponentResolver $bundleResolver = null,
    ) {
    }

    public function handle(QueryInterface $query): InvoiceDetail
    {
        assert($query instanceof GetInvoice);

        $invoice = $this->invoices->findById($query->id);
        if ($invoice === null) {
            throw new NotFoundException("Rechnung \"{$query->id}\" wurde nicht gefunden.");
        }

        $customer = $invoice->customerId !== null ? $this->customers->findById($invoice->customerId) : null;
        $contact = $invoice->contactId !== null ? $this->customerContacts->findById($invoice->contactId) : null;
        $paymentMethod = PaymentSelection::resolvePaymentMethod($this->paymentMethods, $invoice->paymentMethodId);
        $bankAccount = PaymentSelection::resolveBankAccount($this->bankAccounts, $invoice->bankAccountId);

        // Hersteller je Artikel wird über einen kleinen Cache (statt pro
        // Position neu) aufgelöst, damit mehrfach vorkommende Artikel in
        // einer Rechnung nicht wiederholt dieselbe Abfrage auslösen (siehe
        // ListArticlesForDisplayHandler für dasselbe, dort bereits
        // etablierte Muster).
        $manufacturerCache = [];
        $manufacturerFor = function (string $manufacturerId) use (&$manufacturerCache) {
            if (!array_key_exists($manufacturerId, $manufacturerCache)) {
                $manufacturerCache[$manufacturerId] = $this->manufacturers->findById($manufacturerId);
            }

            return $manufacturerCache[$manufacturerId];
        };

        $rawItems = $this->invoices->findItemsByInvoice($invoice->id);
        $items = array_values(array_filter(array_map(
            function ($item) use ($manufacturerFor) {
                $article = $this->articles->findById($item->articleId);

                return $article !== null ? new InvoiceItemWithArticle($item, $article, $manufacturerFor(($article->manufacturerId ?? ''))) : null;
            },
            $rawItems
        )));

        // Mitarbeiter je Arbeitszeit-Position wird ebenfalls über einen
        // kleinen Cache aufgelöst (siehe $manufacturerFor oben für die
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

        $rawLaborItems = $this->invoices->findLaborItemsByInvoice($invoice->id);
        $laborItems = array_values(array_filter(array_map(
            function ($item) use ($userFor) {
                $laborTimeArticle = $this->laborTimeArticles->findById($item->laborTimeArticleId);

                return $laborTimeArticle !== null
                    ? new InvoiceLaborItemWithArticle($item, $laborTimeArticle, $userFor($item->userId))
                    : null;
            },
            $rawLaborItems
        )));

        $freeTextItems = $this->invoices->findFreeTextItemsByInvoice($invoice->id);
        $sectionTexts = $this->invoices->findSectionTextsByInvoice($invoice->id);

        $bundleComponents = $this->bundleResolver?->resolve(array_map(static fn ($entry) => $entry->article, $items)) ?? [];
        return new InvoiceDetail($invoice, $customer, $items, $laborItems, $freeTextItems, $sectionTexts, $contact, $paymentMethod, $bankAccount, bundleComponents: $bundleComponents);
    }
}
