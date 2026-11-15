<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Domain\Backup\BackupSettings;
use App\Domain\Backup\BackupSettingsRepositoryInterface;

/** In-memory test double for BackupSettingsRepositoryInterface (see tests/README.md). */
final class InMemoryBackupSettingsRepository implements BackupSettingsRepositoryInterface
{
    public BackupSettings $settings;

    public function __construct(?BackupSettings $settings = null)
    {
        $this->settings = $settings ?? new BackupSettings();
    }

    public function get(): BackupSettings
    {
        return clone $this->settings;
    }

    public function save(BackupSettings $settings): void
    {
        $this->settings = clone $settings;
    }
}
