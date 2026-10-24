<?php

declare(strict_types=1);

namespace App\Application\MinuteProgram\Command;

use App\Application\Command\CommandInterface;

final class CreateMinuteProgram implements CommandInterface
{
    public function __construct(
        public readonly string $rentalId,
        public readonly string $name,
        public readonly ?string $room,
        public readonly string $startAt,
        public readonly bool $jingleBefore,
        public readonly int $handheldMicCount,
        public readonly int $headsetMicCount,
    ) {
    }
}
