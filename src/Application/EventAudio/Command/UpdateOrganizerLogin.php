<?php

declare(strict_types=1);

namespace App\Application\EventAudio\Command;

use App\Application\Command\CommandInterface;

final class UpdateOrganizerLogin implements CommandInterface
{
    public function __construct(
        public readonly string $id,
        public readonly string $rentalId,
        public readonly string $label,
        public readonly ?string $validFrom,
        public readonly ?string $validUntil,
        public readonly bool $enabled,
        public readonly bool $resetPassword,
    ) {
    }
}
