<?php

declare(strict_types=1);

namespace App\Domain\Calendar;

/**
 * Verwaltet, welche User Zugriff auf welchen Kalender haben (Administration
 * hat implizit immer Zugriff auf alle Kalender, siehe CalDavAuthGuard —
 * diese Tabelle bildet nur die EXPLIZIT vergebenen Zugriffe für alle
 * anderen User ab).
 */
interface CalendarAccessRepositoryInterface
{
    /** @return string[] User-IDs mit explizitem Zugriff auf diesen Kalender */
    public function userIdsForCalendar(string $calendarId): array;

    public function hasAccess(string $calendarId, string $userId): bool;

    /**
     * Ersetzt die komplette Zugriffsliste eines Kalenders durch $userIds
     * (leeres Array = niemandem mehr explizit Zugriff geben).
     *
     * @param string[] $userIds
     */
    public function replaceForCalendar(string $calendarId, array $userIds): void;
}
