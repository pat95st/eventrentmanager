<?php

declare(strict_types=1);

namespace App\Domain\Tour;

/** Crew per stop: technicians of the tour with the stop's deviations applied. */
final class TourCrewResolver
{
    /** @var array<string, TourStopTechnician[]> */
    private array $byStop = [];

    /**
     * @param string[] $tourCrew user ids of the tour's technicians (without declined ones)
     * @param TourStopTechnician[] $deviations
     */
    public function __construct(private readonly array $tourCrew, array $deviations)
    {
        foreach ($deviations as $deviation) {
            $this->byStop[$deviation->stopId][] = $deviation;
        }
    }

    /** @return TourStopTechnician[] */
    public function deviations(string $stopId): array
    {
        return $this->byStop[$stopId] ?? [];
    }

    /**
     * @return array<string, ?TourStopTechnician> user id => deviation that put the user there (null = tour crew)
     */
    public function crewFor(string $stopId): array
    {
        $crew = array_fill_keys($this->tourCrew, null);
        foreach ($this->deviations($stopId) as $deviation) {
            switch ($deviation->action) {
                case TourCrewAction::Remove:
                    unset($crew[$deviation->userId]);
                    break;
                case TourCrewAction::Replace:
                    if ($deviation->replacesUserId !== null) {
                        unset($crew[$deviation->replacesUserId]);
                    }
                    $crew[$deviation->userId] = $deviation;
                    break;
                case TourCrewAction::Add:
                    $crew[$deviation->userId] = $deviation;
                    break;
            }
        }

        return $crew;
    }

    /** Is the user (still) needed at any stop other than $exceptStopIds — or at all? */
    public function isNeededAnywhere(string $userId, array $stopIds): bool
    {
        foreach ($stopIds as $stopId) {
            if (array_key_exists($userId, $this->crewFor($stopId))) {
                return true;
            }
        }

        return false;
    }
}
