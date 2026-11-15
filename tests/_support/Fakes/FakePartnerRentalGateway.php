<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Application\Federation\PartnerRentalGatewayInterface;
use App\Domain\Exception\ValidationException;
use App\Domain\Federation\PartnerInstance;
use DateTimeImmutable;

/** Simple stock/event bookkeeping standing in for the real use cases. */
final class FakePartnerRentalGateway implements PartnerRentalGatewayInterface
{
    /** @var array<string, array{id: string, name: string, daily_price: ?float, stock: int}> */
    public array $articles = [];
    /** @var array<string, array{customer: string, name: string, from: DateTimeImmutable, to: DateTimeImmutable, location: ?string, items: array<string, int>, technicians: array<string, string[]>}> */
    public array $rentals = [];
    /** @var array<string, string> customer id => company name */
    public array $customers = [];
    /** @var list<array{id: string, name: string}> */
    public array $technicians = [];
    /** @var list<array{id: string, name: string}> */
    public array $trades = [];
    private int $sequence = 0;

    public function addArticle(string $id, string $name, ?float $price, int $stock): void
    {
        $this->articles[$id] = ['id' => $id, 'name' => $name, 'daily_price' => $price, 'stock' => $stock];
    }

    public function listArticles(?string $search, DateTimeImmutable $from, DateTimeImmutable $to, int $page, int $pageSize): array
    {
        $items = [];
        foreach ($this->articles as $article) {
            if ($search !== null && stripos($article['name'], $search) === false) {
                continue;
            }
            $items[] = [
                'id' => $article['id'], 'name' => $article['name'], 'manufacturer' => null, 'category' => null,
                'daily_price' => $article['daily_price'], 'total' => $article['stock'],
                'available' => $this->availableQuantity($article['id'], $from, $to),
            ];
        }

        return ['items' => array_slice($items, ($page - 1) * $pageSize, $pageSize), 'total' => count($items)];
    }

    public function findRentableArticle(string $articleId): ?array
    {
        $article = $this->articles[$articleId] ?? null;

        return $article !== null ? ['id' => $article['id'], 'name' => $article['name'], 'daily_price' => $article['daily_price']] : null;
    }

    public function availableQuantity(string $articleId, DateTimeImmutable $from, DateTimeImmutable $to): int
    {
        $reserved = 0;
        foreach ($this->rentals as $rental) {
            if ($rental['to'] >= $from && $rental['from'] <= $to) {
                $reserved += $rental['items'][$articleId] ?? 0;
            }
        }

        return max(0, ($this->articles[$articleId]['stock'] ?? 0) - $reserved);
    }

    public function ensureCustomer(PartnerInstance $partner, ?string $contactName, ?string $contactEmail): string
    {
        if ($partner->customerId !== null && isset($this->customers[$partner->customerId])) {
            return $partner->customerId;
        }
        $id = 'customer-' . (++$this->sequence);
        $this->customers[$id] = $partner->name;

        return $id;
    }

    public function createRental(string $customerId, string $eventName, DateTimeImmutable $from, DateTimeImmutable $to, ?string $location): string
    {
        $id = 'rental-' . (++$this->sequence);
        $this->rentals[$id] = ['customer' => $customerId, 'name' => $eventName, 'from' => $from, 'to' => $to, 'location' => $location, 'items' => [], 'technicians' => []];

        return $id;
    }

    public function addRentalItem(string $rentalId, string $articleId, int $quantity): void
    {
        if (!isset($this->articles[$articleId])) {
            throw new ValidationException(['articleId' => 'unknown']);
        }
        $this->rentals[$rentalId]['items'][$articleId] = ($this->rentals[$rentalId]['items'][$articleId] ?? 0) + $quantity;
    }

    public function deleteRental(string $rentalId): void
    {
        unset($this->rentals[$rentalId]);
    }

    public function rentalSummary(string $rentalId): ?array
    {
        $rental = $this->rentals[$rentalId] ?? null;

        return $rental === null ? null : [
            'event_name' => $rental['name'],
            'start_date' => $rental['from']->format('Y-m-d'),
            'end_date' => $rental['to']->format('Y-m-d'),
            'location' => $rental['location'],
        ];
    }

    public function listTechnicians(): array
    {
        return $this->technicians;
    }

    public function listTrades(): array
    {
        return $this->trades;
    }

    public function assignTechnician(string $rentalId, string $userId, array $tradeIds, ?string $note): void
    {
        $this->rentals[$rentalId]['technicians'][$userId] = $tradeIds;
    }

    public function technicianStatuses(string $rentalId): array
    {
        $names = array_column($this->technicians, 'name', 'id');

        return array_map(static fn ($userId) => ['name' => $names[$userId] ?? '?', 'status' => 'Angefragt'], array_keys($this->rentals[$rentalId]['technicians'] ?? []));
    }
}
