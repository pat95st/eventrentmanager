<?php

declare(strict_types=1);

namespace App\Application\EventAudio\Command;

use App\Application\Command\CommandInterface;

final class CancelPlayback implements CommandInterface
{
    public function __construct(
        public readonly string $requestId,
        public readonly string $rentalId,
    ) {
    }
}
