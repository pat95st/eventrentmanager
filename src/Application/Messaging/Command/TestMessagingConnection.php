<?php

declare(strict_types=1);

namespace App\Application\Messaging\Command;

use App\Application\Command\CommandInterface;
use App\Domain\Messaging\MessagingChannel;

final class TestMessagingConnection implements CommandInterface
{
    public function __construct(public readonly MessagingChannel $channel)
    {
    }
}
