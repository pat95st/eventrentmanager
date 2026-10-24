<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Settings\RentalReminderSettings;
use App\Domain\Settings\RentalReminderSettingsRepositoryInterface;
use PDO;
use RuntimeException;

final class MariadbRentalReminderSettingsRepository implements RentalReminderSettingsRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function get(): RentalReminderSettings
    {
        $stmt = $this->pdo->query('SELECT * FROM rental_reminder_settings WHERE id = 1');
        $row = $stmt->fetch();

        if ($row === false) {
            // Sollte durch die Migration nie vorkommen (die Singleton-Zeile
            // wird dort direkt mit angelegt), siehe MariadbInvoiceSettingsRepository
            // für das identische Muster.
            throw new RuntimeException('Erinnerungs-Einstellungen wurden nicht gefunden — fehlt die Migration 067?');
        }

        return new RentalReminderSettings(
            pickupLeadHours: (int) $row['pickup_lead_hours'],
            returnLeadHours: (int) $row['return_lead_hours'],
            checklistLeadHours: (int) $row['checklist_lead_hours'],
            checklistAutoSendEnabled: (bool) $row['checklist_auto_send_enabled'],
        );
    }

    public function save(RentalReminderSettings $settings): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE rental_reminder_settings
             SET pickup_lead_hours = :pickup_lead_hours,
                 return_lead_hours = :return_lead_hours,
                 checklist_lead_hours = :checklist_lead_hours,
                 checklist_auto_send_enabled = :checklist_auto_send_enabled
             WHERE id = 1'
        );

        $stmt->execute([
            'pickup_lead_hours' => $settings->pickupLeadHours,
            'return_lead_hours' => $settings->returnLeadHours,
            'checklist_lead_hours' => $settings->checklistLeadHours,
            'checklist_auto_send_enabled' => (int) $settings->checklistAutoSendEnabled,
        ]);
    }
}
