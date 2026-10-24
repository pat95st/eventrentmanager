<?php

declare(strict_types=1);

namespace App\Domain\Article;

enum ChannelSyncStatus: string
{
    // Kanal ist (noch) nicht aktiviert bzw. wurde deaktiviert.
    case Pending = 'pending';
    // Letzte Übermittlung an den Kanal war erfolgreich.
    case Synced = 'synced';
    // Letzte Übermittlung ist fehlgeschlagen, siehe ArticleChannelListing::$lastError.
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Nicht übermittelt',
            self::Synced => 'Übermittelt',
            self::Failed => 'Fehler bei Übermittlung',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Pending => 'bg-slate-100 text-slate-600 ring-slate-300 dark:bg-slate-800 dark:text-slate-400 dark:ring-slate-600',
            self::Synced => 'bg-emerald-50 text-emerald-700 ring-emerald-300 dark:bg-emerald-950/40 dark:text-emerald-400 dark:ring-emerald-700',
            self::Failed => 'bg-red-50 text-red-700 ring-red-300 dark:bg-red-950/40 dark:text-red-400 dark:ring-red-700',
        };
    }
}
