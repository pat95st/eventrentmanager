<?php

declare(strict_types=1);

namespace App\Application\Rental\Command;

use App\Application\Command\CommandInterface;
use App\Domain\Rental\RentalReminderType;

final class CreateRentalReminder implements CommandInterface
{
    public function __construct(
        public readonly string $rentalId,
        public readonly RentalReminderType $type,
    ) {
    }
}
