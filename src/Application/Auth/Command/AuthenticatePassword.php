<?php

declare(strict_types=1);

namespace App\Application\Auth\Command;

use App\Application\Command\CommandInterface;

final class AuthenticatePassword implements CommandInterface
{
    public function __construct(
        public readonly string $email,
        public readonly string $password,
    ) {
    }
}
