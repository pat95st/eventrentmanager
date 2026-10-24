<?php

declare(strict_types=1);

namespace App\Application\MinuteProgram\Command;

use App\Application\Command\CommandInterface;

final class CreateMinuteProgramTemplateFromProgram implements CommandInterface
{
    public function __construct(
        public readonly string $minuteProgramId,
        public readonly string $name,
    ) {
    }
}
