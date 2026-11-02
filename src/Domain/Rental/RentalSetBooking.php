<?php

declare(strict_types=1);

namespace App\Domain\Rental;

use DateTimeImmutable;

/**
 * In einer Veranstaltung gebuchtes Set (Migration 113) inkl. gewählter
 * Upgrades. $allocations hält die dafür eingeplanten physischen Artikel
 * (Bundle-Bestandteile aufgelöst, ggf. Alternativen) je Artikel-ID —
 * dieselben Mengen stehen zusätzlich als RentalItem.
 */
final class RentalSetBooking
{
    /**
     * @param list<string> $upgradeIds
     * @param array<string, int> $allocations
     */
    public function __construct(
        public readonly string $id,
        public readonly string $rentalId,
        public readonly string $setId,
        public int $quantity,
        public array $upgradeIds,
        public array $allocations,
        public readonly DateTimeImmutable $createdAt,
    ) {
    }
}
