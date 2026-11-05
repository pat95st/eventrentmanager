<?php

declare(strict_types=1);

namespace App\Application\Technician\Command;

use App\Application\Command\CommandInterface;

/** Techniker (erneut) benachrichtigen. */
final class NotifyTechnician implements CommandInterface
{
    public function __construct(
        public readonly string $id,
        public readonly bool $reminder = false,
    ) {
    }
}
