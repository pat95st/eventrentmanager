<?php

declare(strict_types=1);

namespace App\Domain\Rental;

use DateTimeImmutable;

/**
 * Ein in einer Vermietung gebuchtes Bundle (Migration 107). Die
 * physischen Bestandteile stehen zusätzlich als RentalItem (Planung/
 * Ausgabe je Artikel); dieser Datensatz merkt sich, welche Mengen davon zum
 * Bundle gehören.
 */
final class RentalBundle
{
    public function __construct(
        public readonly string $id,
        public readonly string $rentalId,
        public readonly string $bundleArticleId,
        public int $quantity,
        public readonly DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
        /**
         * Tatsächlich belegte Artikel (Migration 111): Artikel-ID => Menge,
         * z. B. eine Alternative statt des Hauptartikels. Leer bei
         * Altbuchungen ohne Zuordnung — dann gelten die Bestandteile.
         *
         * @var array<string, int>
         */
        public array $allocations = [],
    ) {
    }
}
