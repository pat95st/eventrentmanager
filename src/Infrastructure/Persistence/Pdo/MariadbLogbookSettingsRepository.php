<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Logbook\LogbookSettings;
use App\Domain\Logbook\LogbookSettingsRepositoryInterface;
use PDO;

final class MariadbLogbookSettingsRepository implements LogbookSettingsRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function get(): LogbookSettings
    {
        $row = $this->pdo->query('SELECT edit_window_days FROM logbook_settings WHERE id = 1')->fetch(PDO::FETCH_ASSOC);

        return $row ? new LogbookSettings((int) $row['edit_window_days']) : new LogbookSettings();
    }

    public function save(LogbookSettings $settings): void
    {
        $this->pdo->prepare(
            'INSERT INTO logbook_settings (id, edit_window_days) VALUES (1, :days)
             ON DUPLICATE KEY UPDATE edit_window_days = VALUES(edit_window_days)'
        )->execute(['days' => $settings->editWindowDays]);
    }
}
