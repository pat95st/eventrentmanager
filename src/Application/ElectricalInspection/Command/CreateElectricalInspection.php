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
        public readonly ?string $notes,
    ) {
    }
}
