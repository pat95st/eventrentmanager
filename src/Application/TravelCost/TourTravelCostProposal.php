<?php

declare(strict_types=1);

namespace App\Application\TravelCost;

use App\Domain\TravelCost\TravelCostDocumentType;
use App\Domain\TravelCost\TravelCostMode;
use App\Domain\TravelCost\TravelCostSection;
use App\Domain\TravelCost\TravelCostTrip;
use App\Shared\Uuid;

/** Travel costs of a tour as calculated from its route and planned vehicles (migration 182). */
final class TourTravelCostProposal
{
    /**
     * @param TravelCostTrip[] $trips
     * @param list<array{name: string, costPerKm: string, isDefault: bool}> $vehicles
     * @param string[] $hints why no or possibly inaccurate travel costs are calculated
     */
    public function __construct(
        public readonly string $rentalId,
        public readonly bool $enabled,
        public readonly TravelCostMode $mode,
        public readonly array $trips,
        public readonly array $vehicles,
        public readonly string $costPerKm,      // sum of the vehicles' cost per km
        public readonly string $markupPercent,
        public readonly string $ratePerKm,      // billed price per km (cost + markup, whole cents)
        public readonly array $hints,
        public readonly bool $straightLine = false,
    ) {
    }

    /** Whether the tour has travel costs that can be put on a document. */
    public function isBillable(): bool
    {
        return $this->enabled && $this->trips !== [] && $this->vehicles !== [];
    }

    public function totalKilometers(): int
    {
        return array_sum(array_map(static fn (TravelCostTrip $t) => $t->kilometers, $this->trips));
    }

    public function total(): float
    {
        return array_sum(array_map(fn (TravelCostTrip $t) => round($t->kilometers * (float) $this->ratePerKm, 2), $this->trips));
    }

    public function vehiclesLabel(): ?string
    {
        return $this->vehicles !== [] ? implode(' + ', array_column($this->vehicles, 'name')) : null;
    }

    /** "Lager → Hamburg → Kassel → Lager" from the trips. */
    public function routeLabel(): ?string
    {
        if ($this->trips === []) {
            return null;
        }
        $parts = [];
        foreach ($this->trips as $trip) {
            if ($parts === [] || end($parts) !== $trip->from) {
                $parts[] = $trip->from;
            }
            $parts[] = $trip->to;
        }

        return implode(' → ', $parts);
    }

    /** Travel cost section for a document, with the lines in the mode of the tour. */
    public function toSection(TravelCostDocumentType $type, string $documentId): TravelCostSection
    {
        $section = new TravelCostSection(Uuid::v4(), $type, $documentId, $this->mode, $this->vehiclesLabel(), $this->routeLabel(), $this->ratePerKm, $this->trips, [], $this->rentalId);
        $section->rebuildLines();

        return $section;
    }
}
