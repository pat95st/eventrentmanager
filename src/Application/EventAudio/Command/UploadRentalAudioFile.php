<?php

declare(strict_types=1);

namespace App\Application\EventAudio\Command;

use App\Application\Command\CommandInterface;

final class UploadRentalAudioFile implements CommandInterface
{
    public function __construct(
        public readonly string $rentalId,
        public readonly string $kind,
        public readonly string $title,
        public readonly ?string $description,
        public readonly array $uploadedFile,
    ) {
    }
}
