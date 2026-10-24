<?php

declare(strict_types=1);

namespace App\Application\User\Command;

use App\Application\Command\CommandInterface;

/**
 * Schließt die Selbsteinrichtung des Authenticator-Apps-Faktors ab: der
 * Benutzer hat das angezeigte Secret bereits in seine App eingetragen und
 * bestätigt den Besitz mit einem aktuell erzeugten Code (siehe
 * resources/views/profile/security.php).
 */
final class EnableTotp implements CommandInterface
{
    public function __construct(
        public readonly string $userId,
        public readonly string $secret,
        public readonly string $confirmationCode,
    ) {
    }
}
