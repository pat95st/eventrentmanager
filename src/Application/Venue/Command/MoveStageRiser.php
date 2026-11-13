<?php

declare(strict_types=1);

namespace App\Application\Venue\Command;

use App\Application\Command\CommandInterface;

/** Verschiebt einen Riser auf seiner Bühne (Drag-and-drop im Bühnenplan). */
final class MoveStageRiser implements CommandInterface
{
    public function __construct(
        public readonly string $id,
        public readonly string $venueId,
        public readonly string $stageId,
        public readonly string $centerX,
        public readonly string $centerY,
    ) {
    }
}
