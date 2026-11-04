<?php

declare(strict_types=1);

namespace App\Application\Settings\Command;

use App\Application\Command\CommandInterface;

/** Sicherheits-Einstellungen speichern (nur Administratoren, siehe Route). */
final class UpdateSecuritySettings implements CommandInterface
{
    public function __construct(public readonly string $sessionTimeoutMinutes)
    {
    }
}
