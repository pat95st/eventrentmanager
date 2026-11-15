<?php

declare(strict_types=1);

namespace App\Domain\Backup;

/**
 * Settings of the automatic backup (Einstellungen → Sicherung): the backup
 * ZIP built by BackupArchiveBuilder is uploaded by the cron script
 * bin/run-automatic-backup.php to the selected document storage targets
 * (document_storage_targets, the same targets used for document filing).
 *
 * The schedule fields do not trigger anything by themselves — they only
 * determine the crontab line shown to the administrator (cronSchedule()),
 * because the actual scheduling is done by the system's cron daemon.
 */
final class BackupSettings
{
    public const DEFAULT_TARGET_PATH = 'Sicherungen/{year}/{filename}';

    /** @param list<string> $storageTargetIds */
    public function __construct(
        public bool $enabled = false,
        public array $storageTargetIds = [],
        public string $targetPath = self::DEFAULT_TARGET_PATH,
        public BackupInterval $interval = BackupInterval::Daily,
        public int $hour = 3,
        public int $minute = 15,
        // 1 = Monday … 7 = Sunday (weekly only).
        public int $weekday = 1,
        // 1–28 (monthly only), so the run happens in every month.
        public int $monthDay = 1,
    ) {
    }

    /** The five time fields of the crontab line, e.g. "15 3 * * *". */
    public function cronSchedule(): string
    {
        return match ($this->interval) {
            BackupInterval::Daily => sprintf('%d %d * * *', $this->minute, $this->hour),
            // cron counts Sunday as 0.
            BackupInterval::Weekly => sprintf('%d %d * * %d', $this->minute, $this->hour, $this->weekday % 7),
            BackupInterval::Monthly => sprintf('%d %d %d * *', $this->minute, $this->hour, $this->monthDay),
        };
    }

    public function isTargetSelected(string $storageTargetId): bool
    {
        return in_array($storageTargetId, $this->storageTargetIds, true);
    }
}
