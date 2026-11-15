<?php

declare(strict_types=1);

namespace App\Domain\MinuteProgram;

/**
 * Pure schedule arithmetic shared by the read model
 * (App\Application\MinuteProgram\Query\MinuteProgramDetailBuilder) and the
 * live commands (StartMinuteProgramItemHandler): an item's planned start is
 * the program start plus the summed durations of all items before it. Items
 * without a duration contribute 0 minutes.
 */
final class MinuteProgramSchedule
{
    /**
     * @param MinuteProgramItem[] $items sorted by sort_order
     *
     * @return array<string, int> item id => offset in minutes from the program start
     */
    public static function plannedOffsets(array $items): array
    {
        $offsets = [];
        $cumulative = 0;
        foreach ($items as $item) {
            $offsets[$item->id] = $cumulative;
            $cumulative += $item->durationMinutes ?? 0;
        }

        return $offsets;
    }

    /** @param MinuteProgramItem[] $items */
    public static function totalMinutes(array $items): int
    {
        $total = 0;
        foreach ($items as $item) {
            $total += $item->durationMinutes ?? 0;
        }

        return $total;
    }
}
