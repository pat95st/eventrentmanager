<?php

declare(strict_types=1);

namespace App\Application\Push\Command;

use App\Application\Command\CommandInterface;

final class SubscribeToPush implements CommandInterface
{
    public function __construct(
        public readonly string $userId,
        public readonly string $endpoint,
        public readonly string $p256dh,
        public readonly string $auth,
        public readonly ?string $userAgent,
    ) {
    }
}
