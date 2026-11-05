<?php

declare(strict_types=1);

namespace App\Application\Technician\Command;

use App\Application\Command\CommandInterface;

/** Zu- oder Absage eines Technikers über seinen Login. */
final class RespondToTechnicianRequest implements CommandInterface
{
    public function __construct(
        public readonly string $id,
        public readonly string $userId,
        public readonly bool $accept,
    ) {
    }
}
