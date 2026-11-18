<?php

declare(strict_types=1);

namespace App\Domain\RentalSeries;

/** Kind of rhythm of a rental series (migration 176). */
enum RecurrenceType: string
{
    case Weekly = 'weekly';
    case MonthlyDay = 'monthly_day';
    case MonthlyWeekday = 'monthly_weekday';
    case Yearly = 'yearly';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Weekly => 'Wöchentlich',
            self::MonthlyDay => 'Monatlich am Kalendertag',
            self::MonthlyWeekday => 'Monatlich am n-ten Wochentag',
            self::Yearly => 'Jährlich',
            self::Manual => 'Freie Terminliste',
        };
    }
}
