<?php

declare(strict_types=1);

namespace App\Application\Invoice\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Article\ArticleRepositoryInterface;
use App\Domain\Customer\CustomerRepositoryInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Invoice\InvoiceRepositoryInterface;
use App\Domain\LaborTime\LaborTimeArticleRepositoryInterface;

final class GetInvoiceHandler implements QueryHandlerInterface
{
    public function __construct(
        private readonly InvoiceRepositoryInterface $invoices,
        private readonly CustomerRepositoryInterface $customers,
        private readonly ArticleRepositoryInterface $articles,
        private readonly LaborTimeArticleRepositoryInterface $laborTimeArticles,
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

        $rawItems = $this->invoices->findItemsByInvoice($invoice->id);
        $items = array_values(array_filter(array_map(
            function ($item) {
                $article = $this->articles->findById($item->articleId);

                return $article !== null ? new InvoiceItemWithArticle($item, $article) : null;
            },
            $rawItems
        )));

        $rawLaborItems = $this->invoices->findLaborItemsByInvoice($invoice->id);
        $laborItems = array_values(array_filter(array_map(
            function ($item) {
                $laborTimeArticle = $this->laborTimeArticles->findById($item->laborTimeArticleId);

                return $laborTimeArticle !== null ? new InvoiceLaborItemWithArticle($item, $laborTimeArticle) : null;
            },
            $rawLaborItems
        )));

        $freeTextItems = $this->invoices->findFreeTextItemsByInvoice($invoice->id);
        $sectionTexts = $this->invoices->findSectionTextsByInvoice($invoice->id);

        return new InvoiceDetail($invoice, $customer, $items, $laborItems, $freeTextItems, $sectionTexts);
    }
}
