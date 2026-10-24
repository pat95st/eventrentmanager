<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Domain\Calendar\CalendarAccessRepositoryInterface;

/** In-Memory-Test-Double für CalendarAccessRepositoryInterface, siehe InMemoryCalendarRepository. */
final class InMemoryCalendarAccessRepository implements CalendarAccessRepositoryInterface
{
    /** @var array<string, string[]> Kalender-ID => User-IDs */
    private array $access = [];

    public function userIdsForCalendar(string $calendarId): array
    {
        return $this->access[$calendarId] ?? [];
    }

    public function hasAccess(string $calendarId, string $userId): bool
    {
        return in_array($userId, $this->access[$calendarId] ?? [], true);
    }

    public function replaceForCalendar(string $calendarId, array $userIds): void
    {
        $this->access[$calendarId] = array_values($userIds);
    }
}
