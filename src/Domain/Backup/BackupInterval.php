<?php

declare(strict_types=1);

namespace App\Domain\Backup;

/** How often the automatic backup runs (see BackupSettings::cronSchedule()). */
enum BackupInterval: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';

    public function label(): string
    {
        return match ($this) {
            self::Daily => 'Täglich',
            self::Weekly => 'Wöchentlich',
            self::Monthly => 'Monatlich',
        };
    }
}
