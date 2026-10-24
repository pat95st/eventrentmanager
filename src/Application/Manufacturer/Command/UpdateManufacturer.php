<?php

declare(strict_types=1);

namespace App\Application\Manufacturer\Command;

use App\Application\Command\CommandInterface;

final class UpdateManufacturer implements CommandInterface
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $prefix,
    ) {
    }
}
