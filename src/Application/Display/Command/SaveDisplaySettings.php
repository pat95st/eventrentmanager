<?php

declare(strict_types=1);

namespace App\Application\Display\Command;

use App\Application\Command\CommandInterface;

final class SaveDisplaySettings implements CommandInterface
{
    public function __construct(
        public readonly int $pollIntervalSeconds,
        public readonly int $warnSeconds,
        public readonly int $criticalSeconds,
    ) {
    }
}
