<?php

declare(strict_types=1);

namespace App\Application\User\Command;

use App\Application\Command\CommandInterface;

/**
 * Speichert, welche Dashboard-Bereiche (siehe App\Domain\Dashboard\
 * DashboardWidget) der angemeldete Benutzer auf seiner Startseite sehen
 * möchte. Der Controller übergibt hier ausschließlich die eigene userId
 * (aus CurrentUser::get()), analog zu UpdateDefaultPageSize — jeder
 * Benutzer kann so nur seine eigene Auswahl ändern.
 */
final class UpdateDashboardWidgets implements CommandInterface
{
    /** @param string[] $widgets Rohe Widget-Schlüssel aus dem Formular (werden im Handler validiert). */
    public function __construct(
        public readonly string $userId,
        public readonly array $widgets,
    ) {
    }
}
