<?php

declare(strict_types=1);

namespace App\Application\Api\Command;

use App\Application\Command\CommandInterface;

final class SetApiTokenEnabled implements CommandInterface
{
    public function __construct(
        public readonly string $id,
        public readonly bool $enabled,
    ) {
    }
}
