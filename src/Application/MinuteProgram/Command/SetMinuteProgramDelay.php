<?php

declare(strict_types=1);

namespace App\Application\MinuteProgram\Command;

use App\Application\Command\CommandInterface;

final class SetMinuteProgramDelay implements CommandInterface
{
    public function __construct(
        public readonly string $minuteProgramId,
        public readonly int $currentDelayMinutes,
    ) {
    }
}
