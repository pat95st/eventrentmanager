<?php

declare(strict_types=1);

namespace App\Application\Calendar\Command;

use App\Application\Command\CommandInterface;

final class CreateCalDavCredential implements CommandInterface
{
    public function __construct(
        public readonly string $userId,
        public readonly ?string $name,
    ) {
    }
}
