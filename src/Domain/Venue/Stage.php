<?php

declare(strict_types=1);

namespace App\Domain\Venue;

use DateTimeImmutable;

/**
 * Eine Bühne an einem Veranstaltungsort (ein Veranstaltungsort kann mehrere
 * haben). Alle Längen sind in Metern gespeichert, damit sich daraus später
 * maßstäbliche Bühnenpläne für Veranstaltungen zeichnen lassen.
 */
final class Stage
{
    /** Obergrenze für Breite/Tiefe/Höhe (DECIMAL(5,2)). */
    public const MAX_DIMENSION_M = 999.99;

    /** Obergrenze für die Entfernung zu einem Stromanschluss (DECIMAL(6,2)). */
    public const MAX_DISTANCE_M = 9999.99;

    /**
     * @param array<string, float> $powerDistances Stromanschluss-ID => Entfernung in Metern.
     *                                             Fehlt ein Anschluss, ist seine Entfernung nicht erfasst.
     */
    public function __construct(
        public readonly string $id,
        public readonly string $venueId,
        public string $name,
        public float $widthM,
        public float $depthM,
        public float $heightM,
        public StageType $type,
        public array $powerDistances,
        public readonly DateTimeImmutable $createdAt,
    ) {
    }

    public function areaSquareMeters(): float
    {
        return round($this->widthM * $this->depthM, 2);
    }

    public function distanceTo(string $powerConnectionId): ?float
    {
        return $this->powerDistances[$powerConnectionId] ?? null;
    }
}
