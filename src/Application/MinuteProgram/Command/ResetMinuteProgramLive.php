<?php

declare(strict_types=1);

namespace App\Application\MinuteProgram\Command;

use App\Application\Command\CommandInterface;

/** Resets delay, started item and finished marker (back to the plan). */
final class ResetMinuteProgramLive implements CommandInterface
{
    public function __construct(public readonly string $minuteProgramId)
    {
    }
}
