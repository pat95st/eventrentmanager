<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Backup\BackupInterval;
use App\Domain\Backup\BackupSettings;
use App\Domain\Backup\BackupSettingsRepositoryInterface;
use PDO;

final class MariadbBackupSettingsRepository implements BackupSettingsRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function get(): BackupSettings
    {
        $row = $this->pdo->query('SELECT * FROM backup_settings WHERE id = 1')->fetch();

        // Missing row (should not happen after migration 156): defaults.
        if ($row === false) {
            return new BackupSettings();
        }

        /** @var list<string> $targetIds */
        $targetIds = array_map(
            'strval',
            $this->pdo->query('SELECT storage_target_id FROM backup_storage_targets ORDER BY storage_target_id')->fetchAll(PDO::FETCH_COLUMN),
        );

        return new BackupSettings(
            enabled: (bool) $row['enabled'],
            storageTargetIds: array_values($targetIds),
            targetPath: (string) $row['target_path'],
            interval: BackupInterval::tryFrom((string) $row['schedule_interval']) ?? BackupInterval::Daily,
            hour: (int) $row['run_hour'],
            minute: (int) $row['run_minute'],
            weekday: (int) $row['run_weekday'],
            monthDay: (int) $row['run_month_day'],
        );
    }

    public function save(BackupSettings $settings): void
    {
        $this->pdo->beginTransaction();

        try {
            $this->pdo->prepare(
                'INSERT INTO backup_settings (id, enabled, target_path, schedule_interval, run_hour, run_minute, run_weekday, run_month_day)
                 VALUES (1, :enabled, :target_path, :schedule_interval, :run_hour, :run_minute, :run_weekday, :run_month_day)
                 ON DUPLICATE KEY UPDATE enabled = VALUES(enabled), target_path = VALUES(target_path),
                    schedule_interval = VALUES(schedule_interval), run_hour = VALUES(run_hour), run_minute = VALUES(run_minute),
                    run_weekday = VALUES(run_weekday), run_month_day = VALUES(run_month_day)',
            )->execute([
                'enabled' => $settings->enabled ? 1 : 0,
                'target_path' => $settings->targetPath,
                'schedule_interval' => $settings->interval->value,
                'run_hour' => $settings->hour,
                'run_minute' => $settings->minute,
                'run_weekday' => $settings->weekday,
                'run_month_day' => $settings->monthDay,
            ]);

            $this->pdo->exec('DELETE FROM backup_storage_targets');
            $insert = $this->pdo->prepare('INSERT INTO backup_storage_targets (storage_target_id) VALUES (:id)');
            foreach ($settings->storageTargetIds as $id) {
                $insert->execute(['id' => $id]);
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();

            throw $e;
        }
    }
}
