<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Technician\TechnicianSettings;
use App\Domain\Technician\TechnicianSettingsRepositoryInterface;
use PDO;

/** Einstellungen der Techniker-Anfragen (Migration 134). */
final class MariadbTechnicianSettingsRepository implements TechnicianSettingsRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function get(): TechnicianSettings
    {
        $value = $this->pdo->query('SELECT reminder_hours FROM technician_settings WHERE id = 1')->fetchColumn();

        return new TechnicianSettings(TechnicianSettings::parseHours($value === false ? '24,72' : (string) $value) ?? [24, 72]);
    }

    public function save(TechnicianSettings $settings): void
    {
        $this->pdo->prepare(
            'INSERT INTO technician_settings (id, reminder_hours, updated_at) VALUES (1, :hours, NOW())
             ON DUPLICATE KEY UPDATE reminder_hours = VALUES(reminder_hours), updated_at = VALUES(updated_at)'
        )->execute(['hours' => implode(',', $settings->reminderHours)]);
    }
}
