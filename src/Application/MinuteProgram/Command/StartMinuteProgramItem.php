<?php

declare(strict_types=1);

namespace App\Application\MinuteProgram\Command;

use App\Application\Command\CommandInterface;
use DateTimeImmutable;

/**
 * "Start" pressed at FOH for a program item: records the actual start and
 * derives the delay from it (actual start − planned start).
 */
final class StartMinuteProgramItem implements CommandInterface
{
    public function __construct(
        public readonly string $minuteProgramId,
        public readonly string $itemId,
        // null = now
        public readonly ?DateTimeImmutable $startedAt = null,
    ) {
    }
}
