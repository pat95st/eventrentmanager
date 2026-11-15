<?php

declare(strict_types=1);

namespace App\Application\Backup\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Backup\BackupInterval;
use App\Domain\Backup\BackupSettings;
use App\Domain\Backup\BackupSettingsRepositoryInterface;
use App\Domain\DocumentStorage\DocumentStorageTargetRepositoryInterface;
use App\Domain\Exception\ValidationException;

final class UpdateBackupSettingsHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly BackupSettingsRepositoryInterface $settings,
        private readonly DocumentStorageTargetRepositoryInterface $targets,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof UpdateBackupSettings);

        $errors = [];

        $storageTargetIds = [];
        foreach (array_unique($command->storageTargetIds) as $id) {
            $id = trim((string) $id);
            if ($id === '') {
                continue;
            }
            if ($this->targets->findById($id) === null) {
                $errors['backup_storage_targets'] = 'Mindestens eines der gewählten Speicherziele existiert nicht mehr.';

                continue;
            }
            $storageTargetIds[] = $id;
        }
        if ($command->enabled && $storageTargetIds === []) {
            $errors['backup_storage_targets'] ??= 'Für die automatische Sicherung bitte mindestens ein Speicherziel auswählen.';
        }

        $targetPath = trim((string) $command->targetPath);
        if ($targetPath === '') {
            $targetPath = BackupSettings::DEFAULT_TARGET_PATH;
        }
        if (mb_strlen($targetPath) > 500) {
            $errors['backup_target_path'] = 'Der Ablagepfad darf höchstens 500 Zeichen lang sein.';
        } elseif (str_ends_with($targetPath, '/')) {
            $errors['backup_target_path'] = 'Der Ablagepfad muss mit einem Dateinamen enden (z. B. "{filename}"), nicht mit "/".';
        } elseif (str_contains($targetPath, '..')) {
            $errors['backup_target_path'] = 'Der Ablagepfad darf kein ".." enthalten.';
        }

        $interval = BackupInterval::tryFrom((string) $command->interval);
        if ($interval === null) {
            $errors['backup_interval'] = 'Bitte einen gültigen Rhythmus wählen.';
            $interval = BackupInterval::Daily;
        }

        $hour = 3;
        $minute = 15;
        if (
            preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?$/', trim((string) $command->runTime), $m) === 1
            && (int) $m[1] <= 23 && (int) $m[2] <= 59
        ) {
            $hour = (int) $m[1];
            $minute = (int) $m[2];
        } else {
            $errors['backup_run_time'] = 'Bitte eine gültige Uhrzeit (HH:MM) angeben.';
        }

        $weekday = $this->intInRange($command->weekday, 1, 7, 1);
        if ($weekday === null) {
            $errors['backup_weekday'] = 'Bitte einen gültigen Wochentag wählen.';
        }

        $monthDay = $this->intInRange($command->monthDay, 1, 28, 1);
        if ($monthDay === null) {
            $errors['backup_month_day'] = 'Der Tag des Monats muss zwischen 1 und 28 liegen.';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $this->settings->save(new BackupSettings(
            enabled: $command->enabled,
            storageTargetIds: $storageTargetIds,
            targetPath: $targetPath,
            interval: $interval,
            hour: $hour,
            minute: $minute,
            weekday: (int) $weekday,
            monthDay: (int) $monthDay,
        ));

        return null;
    }

    /** $default for empty input, null for invalid input. */
    private function intInRange(?string $value, int $min, int $max, int $default): ?int
    {
        $value = trim((string) $value);
        if ($value === '') {
            return $default;
        }
        if (preg_match('/^\d{1,2}$/', $value) !== 1) {
            return null;
        }
        $int = (int) $value;

        return $int >= $min && $int <= $max ? $int : null;
    }
}
