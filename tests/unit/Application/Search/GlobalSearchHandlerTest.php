<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Search;

use App\Application\Search\GlobalSearchReadModelInterface;
use App\Application\Search\Query\GlobalSearch;
use App\Application\Search\Query\GlobalSearchHandler;
use App\Application\Search\SearchResult;
use App\Application\Search\SearchResultType;
use App\Application\Search\SearchTarget;
use App\Domain\User\Permission;
use App\Domain\User\TwoFactorMethod;
use App\Domain\User\User;
use Codeception\Test\Unit;
use DateTimeImmutable;

final class GlobalSearchHandlerTest extends Unit
{
    public function testAdministratorSearchesEveryGroupInDisplayOrder(): void
    {
        $readModel = $this->readModel();
        $results = (new GlobalSearchHandler($readModel))->handle(new GlobalSearch('  sommer   fest ', $this->user(Permission::Administration)));

        $this->assertSame(
            ['rental', 'article', 'customer', 'supplier', 'technician', 'venue', 'purchase_order', 'receipt', 'document'],
            array_map(static fn (SearchResult $result): string => $result->target->type()->value, $results)
        );
        // Whitespace is trimmed and collapsed before searching.
        $this->assertSame(['sommer fest'], array_unique(array_column($readModel->calls, 'term')));
        $this->assertNull($readModel->calls[0]['technician']);
    }

    public function testGroupsFollowThePermissionsOfTheUser(): void
    {
        $this->assertSame(
            ['article', 'supplier', 'purchase_order', 'receipt'],
            $this->searchedTypes($this->user(Permission::Artikelverwaltung))
        );
        $this->assertSame(['customer'], $this->searchedTypes($this->user(Permission::Kundenverwaltung)));
        $this->assertSame(
            ['rental', 'technician', 'venue', 'document'],
            $this->searchedTypes($this->user(Permission::AusgabeRuecknahme))
        );
        $this->assertSame([], $this->searchedTypes($this->user(Permission::Minutenprogramme)));
    }

    public function testTechnicianOnlySearchesOwnRentals(): void
    {
        $readModel = $this->readModel();
        (new GlobalSearchHandler($readModel))->handle(new GlobalSearch('fest', $this->user(Permission::Techniker)));

        $this->assertSame([['group' => 'rental', 'term' => 'fest', 'technician' => 'user-1']], $readModel->calls);
    }

    public function testTechnicianWithRentalPermissionSearchesAllRentals(): void
    {
        $readModel = $this->readModel();
        (new GlobalSearchHandler($readModel))->handle(new GlobalSearch('fest', $this->user(Permission::Techniker, Permission::AusgabeRuecknahme)));

        $this->assertSame(['rental', 'technician', 'venue', 'document'], array_column($readModel->calls, 'group'));
        $this->assertNull($readModel->calls[0]['technician']);
    }

    public function testTooShortTermSearchesNothing(): void
    {
        $readModel = $this->readModel();
        $handler = new GlobalSearchHandler($readModel);

        $this->assertSame([], $handler->handle(new GlobalSearch(' a ', $this->user(Permission::Administration))));
        $this->assertSame([], $handler->handle(new GlobalSearch('', $this->user(Permission::Administration))));
        $this->assertSame([], $readModel->calls);
    }

    public function testSearchableTypesMatchTheSearchedGroups(): void
    {
        $user = $this->user(Permission::Artikelverwaltung, Permission::Kundenverwaltung);

        $this->assertSame(
            $this->searchedTypes($user),
            array_map(static fn (SearchResultType $type): string => $type->value, GlobalSearchHandler::searchableTypes($user))
        );
    }

    /** @return string[] */
    private function searchedTypes(User $user): array
    {
        $readModel = $this->readModel();
        (new GlobalSearchHandler($readModel))->handle(new GlobalSearch('sommer', $user));

        return array_column($readModel->calls, 'group');
    }

    private function user(Permission ...$permissions): User
    {
        $now = new DateTimeImmutable('2026-01-01 12:00:00');

        return new User('user-1', 'user@example.org', null, 'Test', 'User', $permissions, TwoFactorMethod::None, null, false, false, true, $now, $now);
    }

    /** Read model that records which groups were searched and returns one hit per group. */
    private function readModel(): GlobalSearchReadModelInterface
    {
        return new class implements GlobalSearchReadModelInterface {
            /** @var array<int, array{group: string, term: string, technician: ?string}> */
            public array $calls = [];

            public function searchRentals(string $term, int $limit, ?string $onlyForTechnicianUserId = null): array
            {
                return $this->hit(SearchTarget::Rental, $term, $onlyForTechnicianUserId);
            }

            public function searchArticles(string $term, int $limit): array
            {
                return $this->hit(SearchTarget::Article, $term);
            }

            public function searchCustomers(string $term, int $limit): array
            {
                return $this->hit(SearchTarget::Customer, $term);
            }

            public function searchSuppliers(string $term, int $limit): array
            {
                return $this->hit(SearchTarget::Supplier, $term);
            }

            public function searchTechnicians(string $term, int $limit): array
            {
                return $this->hit(SearchTarget::Technician, $term);
            }

            public function searchVenues(string $term, int $limit): array
            {
                return $this->hit(SearchTarget::Venue, $term);
            }

            public function searchPurchaseOrders(string $term, int $limit): array
            {
                return $this->hit(SearchTarget::PurchaseOrder, $term);
            }

            public function searchReceipts(string $term, int $limit): array
            {
                return $this->hit(SearchTarget::Receipt, $term);
            }

            public function searchDocuments(string $term, int $limit): array
            {
                return $this->hit(SearchTarget::Invoice, $term);
            }

            /** @return SearchResult[] */
            private function hit(SearchTarget $target, string $term, ?string $technician = null): array
            {
                $this->calls[] = ['group' => $target->type()->value, 'term' => $term, 'technician' => $technician];

                return [new SearchResult($target, 'id-1', 'Title')];
            }
        };
    }
}
