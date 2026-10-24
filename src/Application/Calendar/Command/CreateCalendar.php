<?php

declare(strict_types=1);

namespace App\Application\Calendar\Command;

use App\Application\Command\CommandInterface;

/** Legt einen neuen Vermietungskalender an (kind = Rental, siehe CalendarKind). */
final class CreateCalendar implements CommandInterface
{
    public function __construct(
        public readonly ?string $name,
        public readonly ?string $categoryId,
        public readonly ?string $manufacturerId,
        public readonly ?string $warehouseId,
        public readonly ?string $venueId,
    ) {
    }
}
