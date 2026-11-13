<?php

declare(strict_types=1);

namespace App\Application\Dashboard;

use App\Application\Command\CommandInterface;

/** Verwirft die eigene Anordnung des Benutzers; es gilt wieder die Standardanordnung. */
final class ResetDashboardLayout implements CommandInterface
{
    public function __construct(public readonly string $userId)
    {
    }
}
