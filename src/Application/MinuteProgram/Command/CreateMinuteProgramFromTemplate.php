<?php

declare(strict_types=1);

namespace App\Application\MinuteProgram\Command;

use App\Application\Command\CommandInterface;

final class CreateMinuteProgramFromTemplate implements CommandInterface
{
    public function __construct(
        public readonly string $templateId,
        public readonly string $rentalId,
        public readonly string $name,
        public readonly ?string $room,
        public readonly string $startAt,
    ) {
    }
}
