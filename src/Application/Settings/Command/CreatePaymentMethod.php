<?php

declare(strict_types=1);

namespace App\Application\Settings\Command;

use App\Application\Command\CommandInterface;

final class CreatePaymentMethod implements CommandInterface
{
    public function __construct(
        public readonly string $name,
        public readonly string $paymentTermDays,
        public readonly ?string $noteText,
        public readonly bool $isDefault,
    ) {
    }
}
