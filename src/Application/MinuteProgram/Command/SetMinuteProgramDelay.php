<?php

declare(strict_types=1);

namespace App\Application\MinuteProgram\Command;

use App\Application\Command\CommandInterface;

final class SetMinuteProgramDelay implements CommandInterface
{
    public function __construct(
        public readonly string $minuteProgramId,
        // Delay in seconds (negative = ahead of schedule).
        public readonly int $delaySeconds,
    ) {
    }
}
