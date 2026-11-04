<?php

declare(strict_types=1);

namespace App\Application\Settings\Command;

use App\Application\Command\CommandInterface;
use App\Domain\Settings\BackgroundSlot;

/** Darstellung eines Hintergrunds (Deckkraft, Einpassung, Ausschnitt, Zoom) speichern. */
final class UpdateCompanyBackgroundStyle implements CommandInterface
{
    public function __construct(
        public readonly BackgroundSlot $slot,
        public readonly int $opacity,
        public readonly string $fit,
        public readonly int $positionX,
        public readonly int $positionY,
        public readonly int $zoom,
    ) {
    }
}
