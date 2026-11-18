<?php

declare(strict_types=1);

namespace App\Domain\Tour;

/**
 * Order of a tour: stops by their event start, waypoints between the stop
 * they follow (after_stop_id) and the next stop, ordered by arrival time.
 */
final class TourPlan
{
    /** @var TourStop[] */
    private array $ordered;

    /** @param TourStop[] $all */
    public function __construct(array $all)
    {
        $stops = array_values(array_filter($all, static fn (TourStop $s) => $s->isStop()));
        usort($stops, static fn (TourStop $a, TourStop $b) => [$a->eventStartAt === null, $a->eventStartAt, $a->createdAt, $a->id] <=> [$b->eventStartAt === null, $b->eventStartAt, $b->createdAt, $b->id]);
        $stopIds = array_map(static fn (TourStop $s) => $s->id, $stops);

        $groups = [];
        foreach ($all as $waypoint) {
            if ($waypoint->isStop()) {
                continue;
            }
            $key = $waypoint->afterStopId !== null && in_array($waypoint->afterStopId, $stopIds, true) ? $waypoint->afterStopId : '';
            $groups[$key][] = $waypoint;
        }
        foreach ($groups as &$group) {
            usort($group, static fn (TourStop $a, TourStop $b) => [$a->arriveAt === null, $a->arriveAt ?? $a->departAt, $a->createdAt, $a->id] <=> [$b->arriveAt === null, $b->arriveAt ?? $b->departAt, $b->createdAt, $b->id]);
        }
        unset($group);

        $this->ordered = $groups[''] ?? [];
        foreach ($stops as $stop) {
            $this->ordered[] = $stop;
            foreach ($groups[$stop->id] ?? [] as $waypoint) {
                $this->ordered[] = $waypoint;
            }
        }
    }

    /** @return TourStop[] stops and waypoints in driving order */
    public function ordered(): array
    {
        return $this->ordered;
    }

    /** @return TourStop[] only the stops (events), in order */
    public function stops(): array
    {
        return array_values(array_filter($this->ordered, static fn (TourStop $s) => $s->isStop()));
    }

    /** 1-based number of a stop among the stops, or null. */
    public function numberOf(string $stopId): ?int
    {
        foreach ($this->stops() as $i => $stop) {
            if ($stop->id === $stopId) {
                return $i + 1;
            }
        }

        return null;
    }

    /** @return TourStop[] stops (events) after $stopId, in order */
    public function stopsAfter(string $stopId): array
    {
        $after = [];
        $found = false;
        foreach ($this->stops() as $stop) {
            if ($found) {
                $after[] = $stop;
            }
            $found = $found || $stop->id === $stopId;
        }

        return $after;
    }
}
