<?php

declare(strict_types=1);

namespace App\Domain\Rental;

interface RentalReminderRepositoryInterface
{
    public function existsForRentalAndType(string $rentalId, RentalReminderType $type): bool;

    public function create(RentalReminder $reminder): void;
}
