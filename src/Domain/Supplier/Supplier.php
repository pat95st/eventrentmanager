<?php

declare(strict_types=1);

namespace App\Domain\Supplier;

use DateTimeImmutable;

final class Supplier
{
    public function __construct(
        public readonly string $id,
        public string $name,
        public SupplierType $type,
        public string $street,        // Straße/Hausnummer
        public string $postalCode,
        public string $city,
        public ?string $phone,
        public ?string $email,
        public ?string $customerNumber, // unsere Kundennummer bei diesem Lieferanten; optional
        public readonly DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {
    }
}
