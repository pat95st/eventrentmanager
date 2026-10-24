<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Settings\InvoiceDueReminderSettings;
use App\Domain\Settings\InvoiceDueReminderSettingsRepositoryInterface;
use PDO;
use RuntimeException;

final class MariadbInvoiceDueReminderSettingsRepository implements InvoiceDueReminderSettingsRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function get(): InvoiceDueReminderSettings
    {
        $stmt = $this->pdo->query('SELECT * FROM invoice_due_reminder_settings WHERE id = 1');
        $row = $stmt->fetch();

        if ($row === false) {
            // Sollte durch die Migration nie vorkommen (die Singleton-Zeile
            // wird dort direkt mit angelegt), siehe MariadbRentalReminderSettingsRepository
            // für das identische Muster.
            throw new RuntimeException('Rechnungs-Erinnerungs-Einstellungen wurden nicht gefunden — fehlt die Migration 086?');
        }

        return new InvoiceDueReminderSettings(
            enabled: (bool) $row['enabled'],
            leadDays: (int) $row['lead_days'],
        );
    }

    public function save(InvoiceDueReminderSettings $settings): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE invoice_due_reminder_settings SET enabled = :enabled, lead_days = :lead_days WHERE id = 1'
        );

        $stmt->execute([
            'enabled' => (int) $settings->enabled,
            'lead_days' => $settings->leadDays,
        ]);
    }
}
