<?php

declare(strict_types=1);

namespace App\Domain\EventAudio;

enum PlaybackStatus: string
{
    case Queued = 'queued';
    case Playing = 'playing';
    case Done = 'done';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'wartet',
            self::Playing => 'läuft',
            self::Done => 'gespielt',
            self::Failed => 'Fehler',
            self::Cancelled => 'abgebrochen',
        };
    }

    public function badgeStyle(): string
    {
        return match ($this) {
            self::Queued => 'background:#fef3c7;color:#92400e',
            self::Playing => 'background:#dbeafe;color:#1e40af',
            self::Done => 'background:#d1fae5;color:#065f46',
            self::Failed => 'background:#fee2e2;color:#991b1b',
            self::Cancelled => 'background:#e2e8f0;color:#475569',
        };
    }

    public function isOpen(): bool
    {
        return $this === self::Queued || $this === self::Playing;
    }
}
