<?php

declare(strict_types=1);

namespace App\Application\EventAudio\Command;

use App\Application\Command\CommandInterface;

final class UploadGong implements CommandInterface
{
    public function __construct(
        public readonly array $uploadedFile,
    ) {
    }
}
