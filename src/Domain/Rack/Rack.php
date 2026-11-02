<?php

declare(strict_types=1);

namespace App\Domain\Rack;

use DateTimeImmutable;

/**
 * Rack (Nutzerwunsch, Migration 115): einzelnes physisches Rack mit eigener
 * Inventarnummer, bestückt mit konkreten Geräte-Exemplaren (RackSlot).
 * Ist $fixedInventoryNumberId gesetzt, gehört das Rack fest zu diesem
 * Geräte-Exemplar und wird bei dessen Ausgabe automatisch mitgegeben.
 */
final class Rack
{
    public function __construct(
        public readonly string $id,
        public string $name,
        public string $inventoryNumber,
        public ?string $manufacturerId,
        public int $heightUnits,
        public ?string $installDepthCm, // als String (DECIMAL), siehe Article::$installDepthCm
        public RackRailType $railType,
        public RackWidth $width,
        public ?string $color,
        public bool $hasShockMount,
        public bool $hasWheels,
        public ?string $fixedInventoryNumberId,
        public ?string $description,
        public readonly DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {
    }
}
