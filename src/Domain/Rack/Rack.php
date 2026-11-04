<?php

declare(strict_types=1);

namespace App\Domain\Rack;

use DateTimeImmutable;

/**
 * Rack-Typ (Migrationen 115/124), z. B. „10 HE Shockmount“: Ausstattung und
 * Maße. Die physischen Racks sind RackExemplar-Datensätze mit eigener
 * Rack-Nummer, Inventurnummer, Bestückung und fester Gerätezuordnung.
 */
final class Rack
{
    public function __construct(
        public readonly string $id,
        public string $name,
        public ?string $prefix, // Kürzel für die Inventurnummern der Exemplare (HERSTELLER-KÜRZEL-0001), Migration 124
        public ?string $manufacturerId,
        public int $heightUnits,
        public ?string $installDepthCm, // als String (DECIMAL), siehe Article::$installDepthCm
        public RackRailType $railType,
        public RackWidth $width,
        public ?string $color,
        public bool $hasShockMount,
        public bool $hasWheels,
        public ?string $description,
        public readonly DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {
    }
}
