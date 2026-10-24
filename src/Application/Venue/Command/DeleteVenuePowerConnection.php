<?php

declare(strict_types=1);

namespace App\Application\Venue\Command;

use App\Application\Command\CommandInterface;

final class DeleteVenuePowerConnection implements CommandInterface
{
    public function __construct(
        public readonly string $id,
        public readonly string $venueId,
    ) {
    }
}
