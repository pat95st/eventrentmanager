<?php

declare(strict_types=1);

namespace App\Application\MinuteProgram\Command;

use App\Application\Command\CommandInterface;

final class RevokeMinuteProgramShareLink implements CommandInterface
{
    public function __construct(public readonly string $shareLinkId)
    {
    }
}
