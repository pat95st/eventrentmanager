<?php

declare(strict_types=1);

namespace App\Domain\Venue;

use DateTimeImmutable;

/**
 * Eine Bühne an einem Veranstaltungsort (ein Veranstaltungsort kann mehrere
 * haben). Alle Längen sind in Metern gespeichert, damit sich daraus später
 * maßstäbliche Bühnenpläne für Veranstaltungen zeichnen lassen.
 *
 * Eine mobile Bühne ist auf eine von zwei Arten beschrieben:
 *   - über feste Maße (Breite × Tiefe) — der Elementbedarf wird daraus
 *     berechnet —, oder
 *   - über den vorhandenen Bestand an Bühnenelementen ($elementStock); dann
 *     sind Breite und Tiefe null, und die baubaren Größen werden vorgeschlagen.
 * Beides rechnet StageElementPlanner bei jeder Anzeige neu aus.
 */
final class Stage
{
    /** Obergrenze für Breite/Tiefe/Höhe (DECIMAL(5,2)). */
    public const MAX_DIMENSION_M = 999.99;

    /** Obergrenze für die Entfernung zu einem Stromanschluss (DECIMAL(6,2)). */
    public const MAX_DISTANCE_M = 9999.99;

    /** Höchstzahl je Elementgröße im Bestand einer mobilen Bühne — hält den Aufbauplaner schnell. */
    public const MAX_ELEMENTS_PER_SIZE = 200;

    /**
     * @param array<string, float> $powerDistances Stromanschluss-ID => Entfernung in Metern.
     *                                             Fehlt ein Anschluss, ist seine Entfernung nicht erfasst.
     * @param array<string, int> $elementStock Elementgröße (StageElementSize->value) => vorhandene Anzahl;
     *                                         nur bei mobilen Bühnen ohne feste Maße gefüllt.
     */
    public function __construct(
        public readonly string $id,
        public readonly string $venueId,
        public string $name,
        public ?float $widthM,
        public ?float $depthM,
        public float $heightM,
        public StageType $type,
        public array $powerDistances,
        public readonly DateTimeImmutable $createdAt,
        public array $elementStock = [],
    ) {
    }

    /** Mobile Bühne, die über ihren Elementbestand statt über feste Maße beschrieben ist? */
    public function isDefinedByElementStock(): bool
    {
        return $this->widthM === null || $this->depthM === null;
    }

    public function areaSquareMeters(): ?float
    {
        return $this->widthM === null || $this->depthM === null ? null : round($this->widthM * $this->depthM, 2);
    }

    /**
     * Aufbaumöglichkeiten aus Bühnenelementen — nur für mobile Bühnen:
     * bei festen Maßen die Varianten des Elementbedarfs, sonst die aus dem
     * Bestand baubaren Bühnengrößen.
     *
     * @return list<StageLayout>
     */
    public function buildSuggestions(): array
    {
        if ($this->type !== StageType::Mobile) {
            return [];
        }
        if ($this->widthM === null || $this->depthM === null) {
            return StageElementPlanner::forStock($this->elementStock);
        }

        return StageElementPlanner::forDimensions($this->widthM, $this->depthM);
    }

    public function distanceTo(string $powerConnectionId): ?float
    {
        return $this->powerDistances[$powerConnectionId] ?? null;
    }
}
