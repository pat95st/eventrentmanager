<?php

declare(strict_types=1);

namespace App\Domain\Manufacturer;

use DateTimeImmutable;

final class Manufacturer
{
    public function __construct(
        public readonly string $id,
        public string $name,
        public ?string $prefix, // Präfix für generierte Inventurnummern, z. B. "BOS"; bei Altdaten ggf. noch null
        public readonly DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {
    }
}
