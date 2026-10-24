<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Rental\RentalReminder;
use App\Domain\Rental\RentalReminderRepositoryInterface;
use App\Domain\Rental\RentalReminderType;
use PDO;

final class MariadbRentalReminderRepository implements RentalReminderRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function existsForRentalAndType(string $rentalId, RentalReminderType $type): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM rental_reminders WHERE rental_id = :rental_id AND type = :type');
        $stmt->execute(['rental_id' => $rentalId, 'type' => $type->value]);

        return $stmt->fetch() !== false;
    }

    public function create(RentalReminder $reminder): void
    {
        // Kein ON DUPLICATE KEY UPDATE (anders als beim üblichen save()-Muster):
        // ein Duplikat hier wäre ein Programmierfehler (der Aufrufer prüft
        // existsForRentalAndType() bereits vorher) — der UNIQUE-Constraint
        // (rental_id, type) fängt eine Race Condition zwischen Prüfung und
        // Einfügen zusätzlich per Datenbankfehler ab (siehe
        // bin/send-rental-reminders.php, das jede Vermietung einzeln per
        // try/catch behandelt).
        $stmt = $this->pdo->prepare(
            'INSERT INTO rental_reminders (id, rental_id, type, sent_at) VALUES (:id, :rental_id, :type, :sent_at)'
        );

        $stmt->execute([
            'id' => $reminder->id,
            'rental_id' => $reminder->rentalId,
            'type' => $reminder->type->value,
            'sent_at' => $reminder->sentAt->format('Y-m-d H:i:s'),
        ]);
    }
}
