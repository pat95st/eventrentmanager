<?php

declare(strict_types=1);

namespace App\Domain\Rack;

use DateTimeImmutable;

/** Eingebautes Geräte-Exemplar ab HE $startUnit (von oben gezählt, ab 1). */
final class RackSlot
{
    public function __construct(
        public readonly string $id,
        public readonly string $rackId,
        public readonly string $inventoryNumberId,
        public int $startUnit,
        public readonly DateTimeImmutable $createdAt,
    ) {
    }
}
