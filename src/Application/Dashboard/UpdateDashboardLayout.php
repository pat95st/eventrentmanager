<?php

declare(strict_types=1);

namespace App\Application\Dashboard;

use App\Application\Command\CommandInterface;

/**
 * Speichert die Anordnung der Startseite für EINEN Benutzer. Der Controller
 * übergibt ausschließlich die eigene userId (aus CurrentUser::get()).
 */
final class UpdateDashboardLayout implements CommandInterface
{
    /** @param array<mixed> $zones ungeprüfte Formulardaten: Bereich => Kachel-Schlüssel */
    public function __construct(
        public readonly string $userId,
        public readonly array $zones,
    ) {
    }
}
