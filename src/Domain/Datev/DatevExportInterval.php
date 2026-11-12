<?php

declare(strict_types=1);

namespace App\Domain\Datev;

use DateTimeImmutable;

/** Rhythmus des automatischen Versands an den Steuerberater (siehe bin/send-datev-export.php). */
enum DatevExportInterval: string
{
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';

    public function label(): string
    {
        return match ($this) {
            self::Monthly => 'Monatlich',
            self::Quarterly => 'Vierteljährlich',
        };
    }

    /**
     * Letzter vollständig abgeschlossener Zeitraum vor $today, z. B. am
     * 12.11.2026 monatlich 01.10.–31.10.2026, vierteljährlich 01.07.–30.09.2026.
     *
     * @return array{0: DateTimeImmutable, 1: DateTimeImmutable}
     */
    public function previousPeriod(DateTimeImmutable $today): array
    {
        $firstOfMonth = $today->setTime(0, 0)->modify('first day of this month');

        if ($this === self::Monthly) {
            $from = $firstOfMonth->modify('-1 month');

            return [$from, $from->modify('last day of this month')];
        }

        $month = (int) $firstOfMonth->format('n');
        $quarterStartMonth = intdiv($month - 1, 3) * 3 + 1;
        $currentQuarterStart = $firstOfMonth->setDate((int) $firstOfMonth->format('Y'), $quarterStartMonth, 1);
        $from = $currentQuarterStart->modify('-3 months');

        return [$from, $currentQuarterStart->modify('-1 day')];
    }
}
