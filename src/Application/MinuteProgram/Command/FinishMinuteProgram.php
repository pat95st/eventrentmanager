<?php

declare(strict_types=1);

namespace App\Application\MinuteProgram\Command;

use App\Application\Command\CommandInterface;
use DateTimeImmutable;

/** Marks the program as finished at FOH (end of the last item). */
final class FinishMinuteProgram implements CommandInterface
{
    public function __construct(
        public readonly string $minuteProgramId,
        public readonly ?DateTimeImmutable $finishedAt = null,
    ) {
    }
}
