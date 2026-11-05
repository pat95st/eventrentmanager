<?php

declare(strict_types=1);

namespace App\Domain\Technician;

use DateTimeImmutable;

/** Ein Techniker an einer Veranstaltung samt bedienten Gewerken (Migration 134). */
final class RentalTechnician
{
    /** @param string[] $tradeIds */
    public function __construct(
        public readonly string $id,
        public readonly string $rentalId,
        public readonly string $userId,
        public RentalTechnicianStatus $status,
        public array $tradeIds,
        public ?string $note,
        public DateTimeImmutable $requestedAt,
        public ?DateTimeImmutable $respondedAt,
        public int $reminderCount,
        public ?DateTimeImmutable $lastNotifiedAt,
        public readonly DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {
    }
}
