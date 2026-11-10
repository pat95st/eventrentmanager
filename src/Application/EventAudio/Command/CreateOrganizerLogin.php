<?php

declare(strict_types=1);

namespace App\Application\EventAudio\Command;

use App\Application\Command\CommandInterface;

final class CreateOrganizerLogin implements CommandInterface
{
    public function __construct(
        public readonly string $rentalId,
        public readonly string $label,
        public readonly string $username,
        public readonly ?string $password,
        public readonly ?string $validFrom,
        public readonly ?string $validUntil,
        public readonly bool $canSchedule = false,
        public readonly bool $canUpload = false,
    ) {
    }
}
