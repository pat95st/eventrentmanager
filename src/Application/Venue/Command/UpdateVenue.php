<?php

declare(strict_types=1);

namespace App\Application\Venue\Command;

use App\Application\Command\CommandInterface;

final class UpdateVenue implements CommandInterface
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $street,
        public readonly string $postalCode,
        public readonly string $city,
        public readonly ?string $country,
        public readonly ?string $notes,
        public readonly ?string $caretakerName = null,
        public readonly ?string $caretakerPhone = null,
    ) {
    }
}
