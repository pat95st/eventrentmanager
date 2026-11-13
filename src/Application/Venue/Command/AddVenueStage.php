<?php

declare(strict_types=1);

namespace App\Application\Venue\Command;

use App\Application\Command\CommandInterface;

final class AddVenueStage implements CommandInterface
{
    /**
     * Längen als Roh-Eingabe in Metern ("8,5" oder "8.5").
     *
     * @param array<string, string> $powerDistances Stromanschluss-ID => Entfernung in Metern (leer = nicht erfasst)
     */
    public function __construct(
        public readonly string $venueId,
        public readonly string $name,
        public readonly string $width,
        public readonly string $depth,
        public readonly string $height,
        public readonly string $type,
        public readonly array $powerDistances = [],
    ) {
    }
}
