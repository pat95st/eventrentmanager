<?php

declare(strict_types=1);

namespace App\Application\Auth\Command;

use App\Application\Command\CommandInterface;

/** Setzt (über einen gültigen Setup-Token) ein neues Passwort, siehe CompletePasswordSetupHandler. */
final class CompletePasswordSetup implements CommandInterface
{
    public function __construct(
        public readonly string $token,
        public readonly string $newPassword,
        public readonly string $newPasswordConfirmation,
    ) {
    }
}
