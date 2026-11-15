<?php

declare(strict_types=1);

namespace App\Application\Federation;

use App\Domain\Federation\PartnerInstance;
use DateTimeImmutable;

/**
 * Local operations the federation needs from the rest of the application
 * (articles, availability, customers, events, technicians). Keeps the
 * federation logic independent from the concrete handlers/repositories.
 */
interface PartnerRentalGatewayInterface
{
    /**
     * Rentable articles with availability in the given period.
     *
     * @return array{items: list<array{id: string, name: string, manufacturer: ?string, category: ?string, daily_price: ?float, total: int, available: int}>, total: int}
     */
    public function listArticles(?string $search, DateTimeImmutable $from, DateTimeImmutable $to, int $page, int $pageSize): array;

    /** @return array{id: string, name: string, daily_price: ?float}|null null if unknown or not rentable */
    public function findRentableArticle(string $articleId): ?array;

    public function availableQuantity(string $articleId, DateTimeImmutable $from, DateTimeImmutable $to): int;

    /** Customer representing the partner; created on first use. Returns the customer id. */
    public function ensureCustomer(PartnerInstance $partner, ?string $contactName, ?string $contactEmail): string;

    public function createRental(string $customerId, string $eventName, DateTimeImmutable $from, DateTimeImmutable $to, ?string $location): string;

    public function addRentalItem(string $rentalId, string $articleId, int $quantity): void;

    public function deleteRental(string $rentalId): void;

    /** @return array{event_name: string, start_date: string, end_date: string, location: ?string}|null */
    public function rentalSummary(string $rentalId): ?array;

    /** @return list<array{id: string, name: string}> active technicians */
    public function listTechnicians(): array;

    /** @return list<array{id: string, name: string}> */
    public function listTrades(): array;

    /** @param string[] $tradeIds */
    public function assignTechnician(string $rentalId, string $userId, array $tradeIds, ?string $note): void;

    /** @return list<array{name: string, status: string}> */
    public function technicianStatuses(string $rentalId): array;
}
