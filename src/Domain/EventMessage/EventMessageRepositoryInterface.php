<?php

declare(strict_types=1);

namespace App\Domain\EventMessage;

interface EventMessageRepositoryInterface
{
    public function save(EventMessage $message): void;

    public function findById(string $id): ?EventMessage;

    /** @return EventMessage[] active messages of the rental, oldest first */
    public function findActiveByRental(string $rentalId): array;

    /** @return EventMessage[] active messages first (newest first), then the latest closed ones */
    public function findRecentByRental(string $rentalId, int $closedLimit = 10): array;
}
