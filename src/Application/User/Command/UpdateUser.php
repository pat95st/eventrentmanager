<?php

declare(strict_types=1);

namespace App\Application\User\Command;

use App\Application\Command\CommandInterface;

final class UpdateUser implements CommandInterface
{
    /** @param string[] $permissions */
    public function __construct(
        public readonly string $id,
        public readonly string $email,
        public readonly string $firstName,
        public readonly string $lastName,
        public readonly array $permissions,
        public readonly bool $isActive,
    ) {
    }
}
