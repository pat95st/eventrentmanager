<?php

declare(strict_types=1);

namespace App\Domain\Rack;

/** Fest einem Gerät zugeordnetes Rack-Exemplar, für die Ausgabe-/Rücknahme-Checklisten. */
final class FixedRack
{
    public function __construct(
        public readonly string $exemplarId,
        public readonly string $rackId,
        public readonly string $name,
        public readonly string $rackNumber,
        public readonly string $inventoryNumber,
    ) {
    }
}
