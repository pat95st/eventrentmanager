<?php

declare(strict_types=1);

namespace App\Application\Api\Command;

use App\Application\Command\CommandInterface;

final class CreateApiToken implements CommandInterface
{
    public function __construct(
        public readonly ?string $name,
        public readonly ?string $ipAllowlist,
    ) {
    }
}
