<?php

declare(strict_types=1);

namespace App\Application\Manufacturer\Command;

use App\Application\Command\CommandInterface;

final class CreateManufacturer implements CommandInterface
{
    public function __construct(
        public readonly string $name,
        public readonly string $prefix,
    ) {
    }
}
