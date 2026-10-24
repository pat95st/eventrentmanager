<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Invoice\InvoiceDueReminder;
use App\Domain\Invoice\InvoiceDueReminderRepositoryInterface;
use PDO;

final class MariadbInvoiceDueReminderRepository implements InvoiceDueReminderRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function existsForInvoice(string $invoiceId): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM invoice_due_reminders WHERE invoice_id = :invoice_id');
        $stmt->execute(['invoice_id' => $invoiceId]);

        return $stmt->fetch() !== false;
    }

    public function create(InvoiceDueReminder $reminder): void
    {
        // Kein ON DUPLICATE KEY UPDATE (anders als beim üblichen save()-Muster):
        // ein Duplikat hier wäre ein Programmierfehler (der Aufrufer prüft
        // existsForInvoice() bereits vorher) — der UNIQUE-Constraint
        // (invoice_id) fängt eine Race Condition zwischen Prüfung und
        // Einfügen zusätzlich per Datenbankfehler ab, siehe
        // MariadbRentalReminderRepository::create() für dasselbe Muster.
        $stmt = $this->pdo->prepare(
            'INSERT INTO invoice_due_reminders (id, invoice_id, sent_at) VALUES (:id, :invoice_id, :sent_at)'
        );

        $stmt->execute([
            'id' => $reminder->id,
            'invoice_id' => $reminder->invoiceId,
            'sent_at' => $reminder->sentAt->format('Y-m-d H:i:s'),
        ]);
    }
}
