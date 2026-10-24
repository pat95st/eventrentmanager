<?php

declare(strict_types=1);

namespace App\Domain\Rental;

/** Art der automatischen Erinnerungs-E-Mail (siehe RentalReminder/bin/send-rental-reminders.php). */
enum RentalReminderType: string
{
    case Pickup = 'pickup';
    case Return = 'return';
    case CustomerChecklist = 'customer_checklist';
}
