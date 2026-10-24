<?php

declare(strict_types=1);

namespace App\Application\Printing\Command;

use App\Application\Command\CommandInterface;

final class UpdateLabelPrinter implements CommandInterface
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $driverType,
        public readonly string $ipAddress,
        public readonly string $port,
        public readonly string $tapeWidthMm,
    ) {
    }
}
