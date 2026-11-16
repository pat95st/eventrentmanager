<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Display\DisplaySettings;
use App\Domain\Display\DisplaySettingsRepositoryInterface;
use PDO;

/** Global display settings (migration 167, single row id = 1). */
final class MariadbDisplaySettingsRepository implements DisplaySettingsRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function get(): DisplaySettings
    {
        $row = $this->pdo->query('SELECT * FROM display_settings WHERE id = 1')->fetch();
        if (!$row) {
            return new DisplaySettings();
        }

        return new DisplaySettings(
            pollIntervalSeconds: (int) $row['poll_interval_seconds'],
            warnSeconds: (int) $row['warn_seconds'],
            criticalSeconds: (int) $row['critical_seconds'],
        );
    }

    public function save(DisplaySettings $settings): void
    {
        $this->pdo->prepare(
            'INSERT INTO display_settings (id, poll_interval_seconds, warn_seconds, critical_seconds, updated_at)
             VALUES (1, :poll, :warn, :critical, NOW())
             ON DUPLICATE KEY UPDATE poll_interval_seconds = VALUES(poll_interval_seconds), warn_seconds = VALUES(warn_seconds),
                critical_seconds = VALUES(critical_seconds), updated_at = NOW()'
        )->execute([
            'poll' => $settings->pollIntervalSeconds,
            'warn' => $settings->warnSeconds,
            'critical' => $settings->criticalSeconds,
        ]);
    }
}
