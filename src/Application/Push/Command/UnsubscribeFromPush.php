<?php

declare(strict_types=1);

namespace App\Application\Push\Command;

use App\Application\Command\CommandInterface;

final class UnsubscribeFromPush implements CommandInterface
{
    public function __construct(
        public readonly string $subscriptionId,
        public readonly string $requestingUserId,
    ) {
    }
}
