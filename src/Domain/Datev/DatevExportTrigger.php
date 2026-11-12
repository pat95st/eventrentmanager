<?php

declare(strict_types=1);

namespace App\Domain\Datev;

/** Wie ein DATEV-Export entstanden ist (für das Export-Protokoll). */
enum DatevExportTrigger: string
{
    case Download = 'download';
    case ManualEmail = 'manual_email';
    case AutoEmail = 'auto_email';

    public function label(): string
    {
        return match ($this) {
            self::Download => 'Heruntergeladen',
            self::ManualEmail => 'Per E-Mail gesendet',
            self::AutoEmail => 'Automatisch gesendet',
        };
    }
}
