<?php

declare(strict_types=1);

namespace App\Application\EventAudio\Command;

use App\Application\Command\CommandInterface;

final class DeleteRentalAudioFile implements CommandInterface
{
    public function __construct(
        public readonly string $id,
        public readonly string $rentalId,
    ) {
    }
}
