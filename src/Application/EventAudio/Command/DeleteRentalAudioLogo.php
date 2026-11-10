<?php

declare(strict_types=1);

namespace App\Application\EventAudio\Command;

use App\Application\Command\CommandInterface;

final class DeleteRentalAudioLogo implements CommandInterface
{
    public function __construct(
        public readonly string $rentalId,
    ) {
    }
}
