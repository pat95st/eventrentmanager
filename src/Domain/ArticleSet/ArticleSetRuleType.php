<?php

declare(strict_types=1);

namespace App\Domain\ArticleSet;

enum ArticleSetRuleType: string
{
    /** Zeitraum liegt innerhalb eines Wochenendes (Freitag bis Montag). */
    case Weekends = 'weekends';
    /** Zeitraum enthält keinen Samstag/Sonntag. */
    case Weekdays = 'weekdays';
    /** Jedes Jahr wiederkehrender Zeitraum (Tag/Monat bis Tag/Monat). */
    case YearlyPeriod = 'yearly_period';
    /** Fester Zeitraum mit Datum von/bis. */
    case DateRange = 'date_range';

    public function label(): string
    {
        return match ($this) {
            self::Weekends => 'Nur am Wochenende (Fr–Mo)',
            self::Weekdays => 'Nur unter der Woche (Mo–Fr)',
            self::YearlyPeriod => 'Jedes Jahr in einem Zeitraum',
            self::DateRange => 'Nur in einem festen Zeitraum',
        };
    }
}
