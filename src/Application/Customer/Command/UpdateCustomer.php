<?php

declare(strict_types=1);

namespace App\Application\Customer\Command;

use App\Application\Command\CommandInterface;

final class UpdateCustomer implements CommandInterface
{
    public function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly ?string $companyName,
        public readonly string $firstName,
        public readonly string $lastName,
        public readonly string $street,
        public readonly ?string $addressLine2,
        public readonly string $postalCode,
        public readonly string $city,
        public readonly ?string $phone,
        public readonly ?string $email,
        public readonly ?string $telegramChatId = null,
    ) {
    }
}
