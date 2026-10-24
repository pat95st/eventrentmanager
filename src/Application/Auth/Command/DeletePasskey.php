<?php

declare(strict_types=1);

namespace App\Application\Auth\Command;

use App\Application\Command\CommandInterface;

final class DeletePasskey implements CommandInterface
{
    public function __construct(
        public readonly string $passkeyId,
        public readonly string $requestingUserId,
    ) {
    }
}
