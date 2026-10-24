<?php

declare(strict_types=1);

namespace App\Application\Calendar\Command;

use App\Application\Command\CommandInterface;

/**
 * Bearbeitet einen Kalender. Beim Event-Kalender wird nur $name
 * übernommen (Filterfelder ignoriert, siehe UpdateCalendarHandler) — der
 * Veranstaltungskalender bleibt bewusst nicht filterbar (siehe CalendarKind).
 */
final class UpdateCalendar implements CommandInterface
{
    public function __construct(
        public readonly string $id,
        public readonly ?string $name,
        public readonly ?string $categoryId,
        public readonly ?string $manufacturerId,
        public readonly ?string $warehouseId,
        public readonly ?string $venueId,
    ) {
    }
}
