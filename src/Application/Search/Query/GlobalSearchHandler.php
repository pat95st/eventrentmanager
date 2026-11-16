<?php

declare(strict_types=1);

namespace App\Application\Search\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Application\Search\GlobalSearchReadModelInterface;
use App\Application\Search\SearchResult;
use App\Application\Search\SearchResultType;
use App\Domain\Technician\TechnicianAccess;
use App\Domain\User\Permission;
use App\Domain\User\User;

/**
 * Global search across all areas of the application. A group is only
 * searched if the user may also open the pages its hits link to — the
 * permissions mirror the route guards in src/bootstrap.php.
 */
final class GlobalSearchHandler implements QueryHandlerInterface
{
    public const MIN_TERM_LENGTH = 2;
    private const MAX_TERM_LENGTH = 100;

    public function __construct(private readonly GlobalSearchReadModelInterface $readModel)
    {
    }

    /**
     * The result groups $user may search, in display order.
     *
     * @return SearchResultType[]
     */
    public static function searchableTypes(User $user): array
    {
        $canRentals = $user->has(Permission::AusgabeRuecknahme);
        $canArticles = $user->has(Permission::Artikelverwaltung);

        $allowed = array_keys(array_filter([
            SearchResultType::Rental->value => $canRentals || TechnicianAccess::isRestricted($user),
            SearchResultType::Article->value => $canArticles,
            SearchResultType::Customer->value => $user->has(Permission::Kundenverwaltung),
            SearchResultType::Supplier->value => $canArticles,
            // Technicians are user accounts; the only page showing one is
            // the user form, which is reserved for administrators.
            SearchResultType::Technician->value => $user->isAdministrator(),
            SearchResultType::Venue->value => $canRentals,
            SearchResultType::PurchaseOrder->value => $canArticles,
            SearchResultType::Receipt->value => $canArticles,
            SearchResultType::Document->value => $canRentals,
        ]));

        return array_map(static fn (string $type): SearchResultType => SearchResultType::from($type), $allowed);
    }

    /** @return SearchResult[] grouped by type, in display order */
    public function handle(QueryInterface $query): array
    {
        assert($query instanceof GlobalSearch);

        $term = mb_substr(trim((string) preg_replace('/\s+/u', ' ', $query->term)), 0, self::MAX_TERM_LENGTH);
        if (mb_strlen($term) < self::MIN_TERM_LENGTH) {
            return [];
        }

        $user = $query->user;
        $limit = max(1, $query->limitPerType);
        // Users who are technicians only see just the events they work on.
        $ownRentalsOnly = TechnicianAccess::isRestricted($user) ? $user->id : null;

        $groups = [];
        foreach (self::searchableTypes($user) as $type) {
            $groups[] = match ($type) {
                SearchResultType::Rental => $this->readModel->searchRentals($term, $limit, $ownRentalsOnly),
                SearchResultType::Article => $this->readModel->searchArticles($term, $limit),
                SearchResultType::Customer => $this->readModel->searchCustomers($term, $limit),
                SearchResultType::Supplier => $this->readModel->searchSuppliers($term, $limit),
                SearchResultType::Technician => $this->readModel->searchTechnicians($term, $limit),
                SearchResultType::Venue => $this->readModel->searchVenues($term, $limit),
                SearchResultType::PurchaseOrder => $this->readModel->searchPurchaseOrders($term, $limit),
                SearchResultType::Receipt => $this->readModel->searchReceipts($term, $limit),
                SearchResultType::Document => $this->readModel->searchDocuments($term, $limit),
            };
        }

        return array_merge([], ...$groups);
    }
}
