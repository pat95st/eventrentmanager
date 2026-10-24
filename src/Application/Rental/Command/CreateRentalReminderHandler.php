<?php

declare(strict_types=1);

namespace App\Application\Rental\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Rental\RentalReminder;
use App\Domain\Rental\RentalReminderRepositoryInterface;
use App\Shared\Uuid;
use DateTimeImmutable;

/**
 * Legt den Log-Eintrag für eine verschickte Abhol-/Rückgabe-Erinnerung an
 * (siehe RentalReminder-Klassendoc). Prüft NICHT selbst auf ein Duplikat —
 * das erledigt bin/send-rental-reminders.php vorher
 * (existsForRentalAndType()), analog CreateDunningNoticeHandler/
 * bin/send-dunning-notices.php. Der UNIQUE-Constraint (rental_id, type) in
 * der Datenbank fängt eine verbleibende Race Condition ab.
 */
final class CreateRentalReminderHandler implements CommandHandlerInterface
{
    public function __construct(private readonly RentalReminderRepositoryInterface $reminders)
    {
    }

    public function handle(CommandInterface $command): string
    {
        assert($command instanceof CreateRentalReminder);

        $reminder = new RentalReminder(
            id: Uuid::v4(),
            rentalId: $command->rentalId,
            type: $command->type,
            sentAt: new DateTimeImmutable(),
        );

        $this->reminders->create($reminder);

        return $reminder->id;
    }
}
