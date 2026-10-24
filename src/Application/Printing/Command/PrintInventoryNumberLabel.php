<?php

declare(strict_types=1);

namespace App\Application\Printing\Command;

use App\Application\Command\CommandInterface;

final class PrintInventoryNumberLabel implements CommandInterface
{
    public function __construct(
        public readonly string $inventoryNumberId,
        public readonly string $printerId,
    ) {
    }
}
