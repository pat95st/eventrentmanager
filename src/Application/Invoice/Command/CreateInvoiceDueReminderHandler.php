<?php

declare(strict_types=1);

namespace App\Application\Invoice\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Invoice\InvoiceDueReminder;
use App\Domain\Invoice\InvoiceDueReminderRepositoryInterface;
use App\Shared\Uuid;
use DateTimeImmutable;

/**
 * Legt den Log-Eintrag für eine verschickte Fälligkeits-Erinnerung an
 * (siehe InvoiceDueReminder-Klassendoc). Prüft NICHT selbst auf ein
 * Duplikat — das erledigt bin/send-invoice-due-reminders.php vorher
 * (existsForInvoice()), analog CreateRentalReminderHandler. Der
 * UNIQUE-Constraint (invoice_id) in der Datenbank fängt eine verbleibende
 * Race Condition ab.
 */
final class CreateInvoiceDueReminderHandler implements CommandHandlerInterface
{
    public function __construct(private readonly InvoiceDueReminderRepositoryInterface $reminders)
    {
    }

    public function handle(CommandInterface $command): string
    {
        assert($command instanceof CreateInvoiceDueReminder);

        $reminder = new InvoiceDueReminder(
            id: Uuid::v4(),
            invoiceId: $command->invoiceId,
            sentAt: new DateTimeImmutable(),
        );

        $this->reminders->create($reminder);

        return $reminder->id;
    }
}
