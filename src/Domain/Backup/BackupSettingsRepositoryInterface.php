<?php

declare(strict_types=1);

namespace App\Domain\Backup;

interface BackupSettingsRepositoryInterface
{
    public function get(): BackupSettings;

    public function save(BackupSettings $settings): void;
}
