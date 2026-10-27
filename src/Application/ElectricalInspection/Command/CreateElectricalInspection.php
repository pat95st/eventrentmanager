<?php

declare(strict_types=1);

namespace App\Application\ElectricalInspection\Command;

use App\Application\Command\CommandInterface;

final class CreateElectricalInspection implements CommandInterface
{
    public function __construct(
        public readonly string $inventoryNumberId,
        public readonly ?string $inspectedAt,  // 'Y-m-d'
        public readonly ?string $validityYears, // '1' oder '2'
        public readonly bool $passed,
        public readonly ?string $inspector,
        public readonly ?string $protectiveConductorResistanceOhm, // Schutzleiterwiderstand in Ω, nur Schutzklasse I
        public readonly ?string $insulationResistanceMohm,         // Isolationswiderstand in MΩ
        public readonly ?string $protectiveConductorCurrentMa,     // Schutzleiterstrom/Ableitstrom in mA, nur Schutzklasse I
        public readonly ?string $touchCurrentMa,                   // Berührungsstrom in mA
        public readonly ?string $notes,
    ) {
    }
}
