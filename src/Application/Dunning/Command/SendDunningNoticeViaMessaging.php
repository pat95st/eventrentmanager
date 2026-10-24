<?php

declare(strict_types=1);

namespace App\Application\Dunning\Command;

use App\Application\Command\CommandInterface;

final class SendDunningNoticeViaMessaging implements CommandInterface
{
    public function __construct(
        public readonly string $dunningNoticeId,
    ) {
    }
}
