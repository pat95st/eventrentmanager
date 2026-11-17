<?php

declare(strict_types=1);

namespace App\Application\User\Command;

use App\Application\Command\CommandInterface;

/**
 * Changes whether holding Shift skips confirmations for the signed-in user
 * (/profil/darstellung). The controller only ever passes the user's own id.
 */
final class UpdateConfirmBypass implements CommandInterface
{
    public function __construct(
        public readonly string $userId,
        public readonly bool $enabled,
    ) {
    }
}
