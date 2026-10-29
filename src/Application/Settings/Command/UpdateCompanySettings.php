<?php

declare(strict_types=1);

namespace App\Application\Settings\Command;

use App\Application\Command\CommandInterface;

final class UpdateCompanySettings implements CommandInterface
{
    public function __construct(
        public readonly ?string $name,
        public readonly ?string $brandName,
        public readonly ?string $street,
        public readonly ?string $postalCode,
        public readonly ?string $city,
        public readonly ?string $country,
        public readonly ?string $logoMaxHeightPx,
        // Fußzeile der Dokumente (Migration 108); null = unverändert lassen.
        public readonly ?string $phone = null,
        public readonly ?string $email = null,
        public readonly ?string $website = null,
    ) {
    }
}
