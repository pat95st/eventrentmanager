<?php

declare(strict_types=1);

namespace App\Domain\Routing;

use App\Domain\Vehicle\Vehicle;

/**
 * Strictest restrictions of all planned vehicles for truck routing: the
 * highest/widest vehicle, the longest/heaviest unit. A car with its trailer
 * forms one unit (lengths and weights add up).
 */
final class VehicleRestrictions
{
    /** @param string[] $missing hints about missing dimensions */
    public function __construct(
        public readonly ?float $heightM = null,
        public readonly ?float $widthM = null,
        public readonly ?float $lengthM = null,
        public readonly ?float $weightT = null,
        public readonly array $missing = [],
    ) {
    }

    /**
     * @param list<array{vehicle: Vehicle, towing: ?Vehicle}> $planned
     */
    public static function fromPlannedVehicles(array $planned): self
    {
        $towingIds = [];
        foreach ($planned as $entry) {
            if ($entry['towing'] !== null) {
                $towingIds[$entry['towing']->id] = true;
            }
        }
        $units = [];
        foreach ($planned as $entry) {
            if ($entry['towing'] !== null) {
                $units[] = [$entry['towing'], $entry['vehicle']]; // car + trailer
            } elseif (!isset($towingIds[$entry['vehicle']->id])) {
                $units[] = [$entry['vehicle']];
            }
        }

        $height = $width = $length = $weight = null;
        $missing = [];
        $labels = ['heightCm' => 'Höhe', 'widthCm' => 'Breite', 'lengthCm' => 'Länge', 'grossWeightKg' => 'zulässiges Gesamtgewicht'];
        foreach ($units as $unit) {
            $unitLength = 0.0;
            $unitWeight = 0.0;
            $lengthKnown = true;
            $weightKnown = true;
            foreach ($unit as $vehicle) {
                foreach ($labels as $field => $label) {
                    if (self::number($vehicle->{$field}) === null) {
                        $missing[] = ucfirst($label) . ' von „' . $vehicle->name . '“ fehlt';
                    }
                }
                $h = self::number($vehicle->heightCm);
                $w = self::number($vehicle->widthCm);
                $height = $h !== null ? max($height ?? 0.0, $h / 100) : $height;
                $width = $w !== null ? max($width ?? 0.0, $w / 100) : $width;
                $l = self::number($vehicle->lengthCm);
                $g = self::number($vehicle->grossWeightKg);
                $lengthKnown = $lengthKnown && $l !== null;
                $weightKnown = $weightKnown && $g !== null;
                $unitLength += ($l ?? 0.0) / 100;
                $unitWeight += ($g ?? 0.0) / 1000;
            }
            if ($lengthKnown) {
                $length = max($length ?? 0.0, $unitLength);
            }
            if ($weightKnown) {
                $weight = max($weight ?? 0.0, $unitWeight);
            }
        }

        return new self(
            $height !== null ? round($height, 2) : null,
            $width !== null ? round($width, 2) : null,
            $length !== null ? round($length, 2) : null,
            $weight !== null ? round($weight, 2) : null,
            array_values(array_unique($missing)),
        );
    }

    public function isEmpty(): bool
    {
        return $this->heightM === null && $this->widthM === null && $this->lengthM === null && $this->weightT === null;
    }

    /** @return array<string, float> */
    public function toArray(): array
    {
        return array_filter(['height' => $this->heightM, 'width' => $this->widthM, 'length' => $this->lengthM, 'weight' => $this->weightT], static fn ($v) => $v !== null);
    }

    public function describe(): string
    {
        $parts = [];
        if ($this->heightM !== null) {
            $parts[] = 'Höhe ' . number_format($this->heightM, 2, ',', '') . ' m';
        }
        if ($this->widthM !== null) {
            $parts[] = 'Breite ' . number_format($this->widthM, 2, ',', '') . ' m';
        }
        if ($this->lengthM !== null) {
            $parts[] = 'Länge ' . number_format($this->lengthM, 2, ',', '') . ' m';
        }
        if ($this->weightT !== null) {
            $parts[] = number_format($this->weightT, 2, ',', '') . ' t';
        }

        return implode(', ', $parts);
    }

    private static function number(?string $value): ?float
    {
        return $value !== null && $value !== '' && is_numeric($value) ? (float) $value : null;
    }
}
