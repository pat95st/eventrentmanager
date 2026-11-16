<?php

declare(strict_types=1);

namespace App\Application\Auth\Command;

use App\Application\Command\CommandInterface;

/** Login with user name (or e-mail address) and password against the LDAP directory. */
final class AuthenticateLdap implements CommandInterface
{
    public function __construct(
        public readonly string $login,
        public readonly string $password,
    ) {
    }
}
