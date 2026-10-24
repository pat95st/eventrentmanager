<?php

declare(strict_types=1);

namespace App\Application\ElectricalInspection\Command;

use App\Application\Command\CommandInterface;

final class UpdateElectricalInspection implements CommandInterface
{
    public function __construct(
        public readonly string $id,
        public readonly ?string $inspectedAt,
        public readonly ?string $validityYears,
        public readonly bool $passed,
        public readonly ?string $inspector,
        public readonly ?string $notes,
    ) {
    }
}
