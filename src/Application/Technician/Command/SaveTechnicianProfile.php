<?php

declare(strict_types=1);

namespace App\Application\Technician\Command;

use App\Application\Command\CommandInterface;

/** Techniker-Einstellungen eines Benutzers. */
final class SaveTechnicianProfile implements CommandInterface
{
    public function __construct(
        public readonly string $userId,
        public readonly bool $requiresConfirmation,
        public readonly ?string $phone,
        public readonly ?string $telegramChatId,
    ) {
    }
}
