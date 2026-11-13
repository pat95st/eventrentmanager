<?php

declare(strict_types=1);

namespace App\Application\Venue\Command;

use App\Application\Command\CommandInterface;

final class UpdateStageRiser implements CommandInterface
{
    /**
     * Längen als Roh-Eingabe in Metern ("1,5" oder "1.5"). Welche Maße
     * gebraucht werden, hängt von der Form ab (siehe RiserShape): $depth
     * wird nur bei Rechteck und Dreieck ausgewertet, $width ist bei Kreis
     * und Halbkreis der Durchmesser. Leere Position = Bühnenmitte.
     */
    public function __construct(
        public readonly string $id,
        public readonly string $venueId,
        public readonly string $stageId,
        public readonly string $name,
        public readonly string $shape,
        public readonly string $width,
        public readonly string $depth,
        public readonly string $height,
        public readonly string $centerX = '',
        public readonly string $centerY = '',
        public readonly string $rotation = '',
    ) {
    }
}
