<?php

declare(strict_types=1);

namespace App\Application\MinuteProgram\Command;

use App\Application\Command\CommandInterface;

final class CreateMinuteProgramTemplate implements CommandInterface
{
    public function __construct(
        public readonly string $name,
        public readonly bool $jingleBefore,
        public readonly int $handheldMicCount,
        public readonly int $headsetMicCount,
    ) {
    }
}
