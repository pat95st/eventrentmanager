<?php

declare(strict_types=1);

namespace App\Application\Federation;

use App\Application\Command\CommandBus;
use App\Application\Customer\Command\CreateCustomer;
use App\Application\Query\QueryBus;
use App\Application\Rental\Command\AddRentalItem;
use App\Application\Rental\Command\CreateRental;
use App\Application\Rental\Command\DeleteRental;
use App\Application\Rental\Query\ArticleAvailability;
use App\Application\Rental\Query\GetArticleAvailability;
use App\Application\Technician\Command\AssignTechnician;
use App\Domain\Article\Article;
use App\Domain\Article\ArticleRepositoryInterface;
use App\Domain\Category\CategoryRepositoryInterface;
use App\Domain\Customer\CustomerRepositoryInterface;
use App\Domain\Customer\CustomerType;
use App\Domain\Federation\PartnerInstance;
use App\Domain\InventoryNumber\InventoryNumberRepositoryInterface;
use App\Domain\Manufacturer\ManufacturerRepositoryInterface;
use App\Domain\Rental\RentalRepositoryInterface;
use App\Domain\Technician\RentalTechnicianRepositoryInterface;
use App\Domain\Technician\TechnicianAccess;
use App\Domain\Technician\TradeRepositoryInterface;
use App\Domain\User\User;
use App\Domain\User\UserRepositoryInterface;
use DateTimeImmutable;

/**
 * Connects the federation to the existing use cases: events and their
 * articles are created through the regular commands (same validation as in
 * the UI), availability comes from the same query as the availability
 * calendar on the article page.
 */
final class DefaultPartnerRentalGateway implements PartnerRentalGatewayInterface
{
    public function __construct(
        private readonly CommandBus $commands,
        private readonly QueryBus $queries,
        private readonly ArticleRepositoryInterface $articles,
        private readonly ManufacturerRepositoryInterface $manufacturers,
        private readonly CategoryRepositoryInterface $categories,
        private readonly InventoryNumberRepositoryInterface $inventoryNumbers,
        private readonly RentalRepositoryInterface $rentals,
        private readonly CustomerRepositoryInterface $customers,
        private readonly UserRepositoryInterface $users,
        private readonly TradeRepositoryInterface $trades,
        private readonly RentalTechnicianRepositoryInterface $rentalTechnicians,
    ) {
    }

    public function listArticles(?string $search, DateTimeImmutable $from, DateTimeImmutable $to, int $page, int $pageSize): array
    {
        $articles = $this->articles->search($search, null, [], $pageSize, ($page - 1) * $pageSize, onlyActive: true, onlyRentableIndividually: true, excludeBundles: true);
        $total = $this->articles->countSearch($search, null, [], onlyActive: true, onlyRentableIndividually: true, excludeBundles: true);
        $counts = $this->inventoryNumbers->countActiveByArticles(array_map(static fn (Article $a) => $a->id, $articles));

        $items = [];
        foreach ($articles as $article) {
            $stock = $counts[$article->id] ?? 0;
            $items[] = [
                'id' => $article->id,
                'name' => $article->name,
                'manufacturer' => $article->manufacturerId !== null ? $this->manufacturers->findById($article->manufacturerId)?->name : null,
                'category' => $article->categoryId !== null ? $this->categories->findById($article->categoryId)?->name : null,
                'daily_price' => $this->price($article),
                'total' => $stock,
                'available' => $stock > 0 ? $this->availableQuantity($article->id, $from, $to) : 0,
            ];
        }

        return ['items' => $items, 'total' => $total];
    }

    public function findRentableArticle(string $articleId): ?array
    {
        $article = $this->articles->findById($articleId);
        if ($article === null || !$article->isActive || $article->isBundle || !$article->isRentableIndividually) {
            return null;
        }

        return ['id' => $article->id, 'name' => $article->name, 'daily_price' => $this->price($article)];
    }

    public function availableQuantity(string $articleId, DateTimeImmutable $from, DateTimeImmutable $to): int
    {
        /** @var ArticleAvailability $availability */
        $availability = $this->queries->ask(new GetArticleAvailability($articleId, $from->format('Y-m-d'), $to->format('Y-m-d')));
        $peak = $availability->reservedByDay === [] ? 0 : max($availability->reservedByDay);

        return max(0, $availability->totalInventory - $peak);
    }

    public function ensureCustomer(PartnerInstance $partner, ?string $contactName, ?string $contactEmail): string
    {
        if ($partner->customerId !== null && $this->customers->findById($partner->customerId) !== null) {
            return $partner->customerId;
        }

        $nameParts = preg_split('/\s+/', trim((string) $contactName)) ?: [];
        $nameParts = array_values(array_filter($nameParts, static fn ($p) => $p !== ''));
        $lastName = count($nameParts) > 1 ? (string) array_pop($nameParts) : 'Partner';
        $firstName = $nameParts !== [] ? implode(' ', $nameParts) : 'Kontakt';
        $profile = $partner->profile;
        $email = $profile->email ?? $contactEmail;
        if ($email !== null && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $email = null;
        }

        // Missing address parts are filled with a dash so the customer can be
        // created automatically; they can be completed in the customer form.
        return (string) $this->commands->dispatch(new CreateCustomer(
            type: CustomerType::Geschaeftlich->value,
            companyName: $partner->name,
            firstName: $firstName,
            lastName: $lastName,
            street: $profile->street ?? '–',
            addressLine2: null,
            postalCode: $profile->postalCode ?? '–',
            city: $profile->city ?? '–',
            phone: $profile->phone,
            email: $email,
        ));
    }

    public function createRental(string $customerId, string $eventName, DateTimeImmutable $from, DateTimeImmutable $to, ?string $location): string
    {
        return (string) $this->commands->dispatch(new CreateRental(
            customerId: $customerId,
            startDate: $from->format('Y-m-d'),
            endDate: $to->format('Y-m-d'),
            eventName: $eventName,
            eventLocation: $location ?? '',
            deploymentDays: (string) ($from->diff($to)->days + 1),
            holidayConfirmed: true,
        ));
    }

    public function addRentalItem(string $rentalId, string $articleId, int $quantity): void
    {
        $this->commands->dispatch(new AddRentalItem($rentalId, $articleId, (string) $quantity));
    }

    public function deleteRental(string $rentalId): void
    {
        $this->commands->dispatch(new DeleteRental($rentalId));
    }

    public function rentalSummary(string $rentalId): ?array
    {
        $rental = $this->rentals->findById($rentalId);
        if ($rental === null) {
            return null;
        }

        return [
            'event_name' => $rental->eventName,
            'start_date' => $rental->startDate->format('Y-m-d'),
            'end_date' => $rental->endDate->format('Y-m-d'),
            'location' => $rental->eventLocation,
        ];
    }

    public function listTechnicians(): array
    {
        $technicians = [];
        foreach ($this->users->findAllActive() as $user) {
            if (TechnicianAccess::isTechnician($user)) {
                $technicians[] = ['id' => $user->id, 'name' => $this->publicName($user)];
            }
        }
        usort($technicians, static fn ($a, $b) => strcasecmp($a['name'], $b['name']));

        return $technicians;
    }

    public function listTrades(): array
    {
        return array_map(static fn ($trade) => ['id' => $trade->id, 'name' => $trade->name], $this->trades->findAll());
    }

    public function assignTechnician(string $rentalId, string $userId, array $tradeIds, ?string $note): void
    {
        $this->commands->dispatch(new AssignTechnician($rentalId, $userId, $tradeIds, $note));
    }

    public function technicianStatuses(string $rentalId): array
    {
        $statuses = [];
        foreach ($this->rentalTechnicians->findByRental($rentalId) as $assignment) {
            $user = $this->users->findById($assignment->userId);
            $statuses[] = [
                'name' => $user !== null ? $this->publicName($user) : 'Techniker',
                'status' => $assignment->status->label(),
            ];
        }

        return $statuses;
    }

    private function price(Article $article): ?float
    {
        return $article->isBillable && $article->dailyRentalPrice !== null ? (float) $article->dailyRentalPrice : null;
    }

    /** Name shared with partners — never the e-mail address. */
    private function publicName(User $user): string
    {
        $name = trim(($user->firstName ?? '') . ' ' . ($user->lastName ?? ''));

        return $name !== '' ? $name : 'Techniker ' . strtoupper(substr($user->id, 0, 4));
    }
}
