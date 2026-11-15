<?php

declare(strict_types=1);

namespace App\Domain\OnlinePayment;

enum OnlinePaymentStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Offen',
            self::Completed => 'Bezahlt',
            self::Failed => 'Fehlgeschlagen',
            self::Cancelled => 'Abgebrochen',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Pending => 'bg-amber-100 text-amber-700 dark:bg-amber-950/40 dark:text-amber-400',
            self::Completed => 'bg-green-100 text-green-700 dark:bg-green-950/40 dark:text-green-400',
            self::Failed => 'bg-red-100 text-red-700 dark:bg-red-950/40 dark:text-red-400',
            self::Cancelled => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
        };
    }

    public function isFinal(): bool
    {
        return $this !== self::Pending;
    }
}
