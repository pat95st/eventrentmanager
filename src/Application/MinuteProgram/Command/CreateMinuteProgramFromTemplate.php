<?php

declare(strict_types=1);

namespace App\Application\MinuteProgram\Command;

use App\Application\Command\CommandInterface;

final class CreateMinuteProgramFromTemplate implements CommandInterface
{
    /** @param list<string>|null $tradeIds */
    public function __construct(
        public readonly string $templateId,
        public readonly string $rentalId,
        public readonly string $name,
        public readonly ?string $room,
        public readonly string $startAt,
        // Migration 172: chosen trade IDs, null = take over the template's selection.
        public readonly ?array $tradeIds = null,
    ) {
    }
}
