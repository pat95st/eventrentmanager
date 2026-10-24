<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use DateTimeImmutable;

/**
 * Baut das Wochenraster eines Monats (Montag–Sonntag, volle Wochenzeilen)
 * für Monatskalender-Ansichten — gemeinsam genutzt vom Vermietungs-Kalender
 * (CalendarController) und dem Verfügbarkeitskalender auf der Artikelseite
 * (ArticleController), damit beide exakt dasselbe Rasterverhalten haben.
 */
final class MonthGridBuilder
{
    /** Liest "?monat=YYYY-MM" (oder Fallback: aktueller Monat) als Erster-des-Monats-Datum. */
    public static function parseMonth(?string $value): DateTimeImmutable
    {
        if ($value !== null && $value !== '') {
            $parsed = DateTimeImmutable::createFromFormat('Y-m-d', $value . '-01');
            if ($parsed !== false) {
                return $parsed->setTime(0, 0);
            }
        }

        return new DateTimeImmutable('first day of this month');
    }

    /**
     * @return array{0: DateTimeImmutable, 1: DateTimeImmutable, 2: DateTimeImmutable, 3: DateTimeImmutable}
     *         [firstOfMonth, lastOfMonth, gridStart, gridEnd]
     */
    public static function bounds(DateTimeImmutable $referenceMonth): array
    {
        $firstOfMonth = $referenceMonth->modify('first day of this month')->setTime(0, 0);
        $lastOfMonth = $referenceMonth->modify('last day of this month')->setTime(0, 0);

        // Kalendergitter beginnt am Montag der ersten Woche und endet am
        // Sonntag der letzten Woche, damit volle Wochenzeilen angezeigt werden.
        $gridStart = $firstOfMonth->modify('monday this week');
        if ($gridStart > $firstOfMonth) {
            $gridStart = $gridStart->modify('-7 days');
        }
        $gridEnd = $lastOfMonth->modify('sunday this week');
        if ($gridEnd < $lastOfMonth) {
            $gridEnd = $gridEnd->modify('+7 days');
        }

        return [$firstOfMonth, $lastOfMonth, $gridStart, $gridEnd];
    }

    /**
     * @return DateTimeImmutable[][] Wochen (je 7 Tage) von $gridStart bis $gridEnd.
     */
    public static function weeks(DateTimeImmutable $gridStart, DateTimeImmutable $gridEnd): array
    {
        $weeks = [];
        $cursor = $gridStart;
        while ($cursor <= $gridEnd) {
            $week = [];
            for ($i = 0; $i < 7; $i++) {
                $week[] = $cursor;
                $cursor = $cursor->modify('+1 day');
            }
            $weeks[] = $week;
        }

        return $weeks;
    }
}
