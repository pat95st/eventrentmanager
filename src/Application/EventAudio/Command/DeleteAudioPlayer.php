<?php

declare(strict_types=1);

namespace App\Application\EventAudio\Command;

use App\Application\Command\CommandInterface;

final class DeleteAudioPlayer implements CommandInterface
{
    public function __construct(
        public readonly string $id,
    ) {
    }
}
