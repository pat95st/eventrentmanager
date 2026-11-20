<?php

declare(strict_types=1);

namespace App\Domain\TravelCost;

use App\Shared\Uuid;

/**
 * Travel cost section of an offer, order confirmation or invoice
 * (migration 182). The values are copied from the tour when they are taken
 * over, so a sent document does not change when the tour changes later.
 * $trips keeps the trips to rebuild the lines when the mode is switched;
 * $lines are the lines printed on the document (editable while the document
 * is a draft). Like labor time, the total is added after discount and
 * accessory flat rate (see App\Domain\Pricing\DocumentTotals).
 */
final class TravelCostSection
{
    /**
     * @param TravelCostTrip[] $trips
     * @param TravelCostLine[] $lines
     */
    public function __construct(
        public readonly string $id,
        public readonly TravelCostDocumentType $documentType,
        public readonly string $documentId,
        public TravelCostMode $mode,
        public ?string $vehiclesLabel,
        public ?string $routeLabel,
        public string $ratePerKm,
        public array $trips,
        public array $lines = [],
        public ?string $sourceRentalId = null,
    ) {
    }

    public function total(): float
    {
        return array_sum(array_map(static fn (TravelCostLine $l) => $l->lineTotal(), $this->lines));
    }

    public function totalKilometers(): float
    {
        return array_sum(array_map(static fn (TravelCostLine $l) => (float) $l->kilometers, $this->lines));
    }

    public function hasDates(): bool
    {
        foreach ($this->lines as $line) {
            if ($line->tripDate !== null) {
                return true;
            }
        }

        return false;
    }

    /** Rebuilds the lines from the stored trips in the current mode (manual changes to lines are lost). */
    public function rebuildLines(): void
    {
        $this->lines = self::buildLines($this->mode, $this->trips, $this->ratePerKm, $this->vehiclesLabel, $this->routeLabel);
    }

    /** Copy of this section for another document (e.g. invoice from an offer). */
    public function copyFor(TravelCostDocumentType $type, string $documentId): self
    {
        return new self(
            Uuid::v4(),
            $type,
            $documentId,
            $this->mode,
            $this->vehiclesLabel,
            $this->routeLabel,
            $this->ratePerKm,
            $this->trips,
            array_map(static fn (TravelCostLine $l) => new TravelCostLine(Uuid::v4(), $l->description, $l->kilometers, $l->ratePerKm, $l->detail, $l->tripDate, $l->sortOrder), $this->lines),
            $this->sourceRentalId,
        );
    }

    /**
     * Summary: one line "Fahrtkosten Tour (n Fahrten), vehicles" with all
     * kilometres and the route below. Itemized: one line per trip with date
     * and "from → to".
     *
     * @param TravelCostTrip[] $trips
     * @return TravelCostLine[]
     */
    public static function buildLines(TravelCostMode $mode, array $trips, string $ratePerKm, ?string $vehiclesLabel, ?string $routeLabel): array
    {
        if ($trips === []) {
            return [];
        }
        if ($mode === TravelCostMode::Summary) {
            $count = count($trips);
            $km = array_sum(array_map(static fn (TravelCostTrip $t) => $t->kilometers, $trips));
            $description = 'Fahrtkosten Tour (' . $count . ' ' . ($count === 1 ? 'Fahrt' : 'Fahrten') . ')'
                . ($vehiclesLabel !== null && $vehiclesLabel !== '' ? ', ' . $vehiclesLabel : '');

            return [new TravelCostLine(Uuid::v4(), mb_substr($description, 0, 255), number_format($km, 2, '.', ''), $ratePerKm, $routeLabel !== null && $routeLabel !== '' ? mb_substr($routeLabel, 0, 1000) : null, null, 0)];
        }

        $lines = [];
        foreach (array_values($trips) as $i => $trip) {
            $lines[] = new TravelCostLine(Uuid::v4(), mb_substr($trip->from . ' → ' . $trip->to, 0, 255), number_format($trip->kilometers, 2, '.', ''), $ratePerKm, null, $trip->date, $i);
        }

        return $lines;
    }

    /** @return string JSON of the trips for storage */
    public function tripsJson(): string
    {
        return json_encode(array_map(static fn (TravelCostTrip $t) => $t->toArray(), array_values($this->trips)), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    /** @return TravelCostTrip[] */
    public static function tripsFromJson(string $json): array
    {
        $data = json_decode($json, true);

        return is_array($data) ? array_values(array_map(static fn ($t) => TravelCostTrip::fromArray(is_array($t) ? $t : []), $data)) : [];
    }
}
