<?php

declare(strict_types=1);

namespace App\Domain\Backup;

enum BackupRunTrigger: string
{
    case Cron = 'cron';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Cron => 'Cronjob',
            self::Manual => 'Manuell',
        };
    }
}
