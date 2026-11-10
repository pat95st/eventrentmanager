<?php

declare(strict_types=1);

namespace App\Domain\EventAudio;

/** Status eines geplanten Einspielers (Migration 136). */
enum ScheduledPlaybackStatus: string
{
    case Pending = 'pending';     // geplant, Startzeit noch nicht erreicht
    case Triggered = 'triggered'; // Abspielauftrag angelegt
    case Missed = 'missed';       // Startzeit verpasst (kein Abruf rechtzeitig)
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'geplant',
            self::Triggered => 'ausgelöst',
            self::Missed => 'verpasst',
            self::Cancelled => 'abgebrochen',
        };
    }

    public function badgeStyle(): string
    {
        return match ($this) {
            self::Pending => 'background:#fef3c7;color:#92400e',
            self::Triggered => 'background:#d1fae5;color:#065f46',
            self::Missed => 'background:#fee2e2;color:#991b1b',
            self::Cancelled => 'background:#e2e8f0;color:#475569',
        };
    }
}
