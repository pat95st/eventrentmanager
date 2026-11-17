<?php

declare(strict_types=1);

namespace App\Application\Search;

/**
 * Read side of the global search: one lookup per result group. Every method
 * returns at most $limit hits, best matches first. Permission checks are NOT
 * done here but in Query\GlobalSearchHandler, which only calls the lookups
 * the current user may see.
 */
interface GlobalSearchReadModelInterface
{
    /**
     * @param string|null $onlyForTechnicianUserId restrict to events this
     *     technician is requested or booked for (users who are technicians
     *     only, see App\Domain\Technician\TechnicianAccess); such hits must
     *     not expose customer data
     * @return SearchResult[]
     */
    public function searchRentals(string $term, int $limit, ?string $onlyForTechnicianUserId = null): array;

    /** @return SearchResult[] articles by name/manufacturer, plus single items by inventory number */
    public function searchArticles(string $term, int $limit): array;

    /** @return SearchResult[] */
    public function searchCustomers(string $term, int $limit): array;

    /** @return SearchResult[] */
    public function searchSuppliers(string $term, int $limit): array;

    /** @return SearchResult[] */
    public function searchTechnicians(string $term, int $limit): array;

    /** @return SearchResult[] */
    public function searchVenues(string $term, int $limit): array;

    /** @return SearchResult[] */
    public function searchPurchaseOrders(string $term, int $limit): array;

    /** @return SearchResult[] */
    public function searchReceipts(string $term, int $limit): array;

    /** @return SearchResult[] offers, order confirmations, invoices and invoice corrections by document number */
    public function searchDocuments(string $term, int $limit): array;
}
