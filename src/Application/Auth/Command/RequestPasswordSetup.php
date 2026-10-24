<?php

declare(strict_types=1);

namespace App\Application\Auth\Command;

use App\Application\Command\CommandInterface;

/** "Passwort vergessen"-Anfrage, siehe RequestPasswordSetupHandler. */
final class RequestPasswordSetup implements CommandInterface
{
    public function __construct(
        public readonly string $email,
        public readonly string $originUrl,
    ) {
    }
}
