<?php

declare(strict_types=1);

namespace App\Domain\Backup;

enum BackupRunStatus: string
{
    // Uploaded to every selected storage target.
    case Success = 'success';
    // Uploaded to at least one, but not all storage targets.
    case Partial = 'partial';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Success => 'Erfolgreich',
            self::Partial => 'Teilweise fehlgeschlagen',
            self::Failed => 'Fehlgeschlagen',
        };
    }
}
