<?php

declare(strict_types=1);

namespace App\Domain\Rack;

use DateTimeImmutable;

/**
 * Eingebautes Geräte-Exemplar ab HE $startUnit (von oben gezählt, ab 1).
 * $side: belegte Hälfte bei einem 9,5-Zoll-Gerät im 19-Zoll-Rack, sonst null
 * (ganze Breite), Migration 128.
 */
final class RackSlot
{
    public function __construct(
        public readonly string $id,
        public readonly string $exemplarId, // Rack-Exemplar (Migration 124)
        public readonly string $inventoryNumberId,
        public int $startUnit,
        public readonly DateTimeImmutable $createdAt,
        public ?RackSlotSide $side = null,
    ) {
    }
}
