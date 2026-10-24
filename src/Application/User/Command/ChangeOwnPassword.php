<?php

declare(strict_types=1);

namespace App\Application\User\Command;

use App\Application\Command\CommandInterface;

final class ChangeOwnPassword implements CommandInterface
{
    public function __construct(
        public readonly string $userId,
        public readonly string $currentPassword,
        public readonly string $newPassword,
    ) {
    }
}
