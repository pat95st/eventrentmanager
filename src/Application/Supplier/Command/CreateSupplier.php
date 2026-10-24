<?php

declare(strict_types=1);

namespace App\Application\Supplier\Command;

use App\Application\Command\CommandInterface;

final class CreateSupplier implements CommandInterface
{
    public function __construct(
        public readonly string $name,
        public readonly string $type,
        public readonly string $street,
        public readonly string $postalCode,
        public readonly string $city,
        public readonly ?string $phone,
        public readonly ?string $email,
        public readonly ?string $customerNumber = null,
    ) {
    }
}
