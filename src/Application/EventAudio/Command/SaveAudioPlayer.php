<?php

declare(strict_types=1);

namespace App\Application\EventAudio\Command;

use App\Application\Command\CommandInterface;

final class SaveAudioPlayer implements CommandInterface
{
    public function __construct(
        public readonly ?string $id,
        public readonly string $name,
        public readonly bool $enabled,
    ) {
    }
}
