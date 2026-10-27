<?php

declare(strict_types=1);

namespace App\Domain\Venue;

use DateTimeImmutable;

/**
 * Ein einzelner Stromanschluss an einem Veranstaltungsort (ein Veranstaltungsort
 * kann mehrere davon haben, z. B. "Bühne links: 400 V CEE 32 A" und
 * "Technikraum: 230 V Schuko"). Die elektrotechnischen Warnungen werden
 * bewusst NICHT als statisches Flag gespeichert, sondern bei jeder Anzeige
 * live aus type/breakerRating/rcdTripCurrent neu berechnet (siehe
 * breakerExceedsRatedCurrent()/needsOwnDistributorWithRcd()), damit eine
 * spätere Korrektur der Bemessungsstrom-Tabelle (PowerConnectionType) oder
 * ein nachträglich geänderter Wert sofort korrekt angezeigt wird.
 */
final class PowerConnection
{
    public function __construct(
        public readonly string $id,
        public readonly string $venueId,
        public PowerConnectionType $type,
        public CircuitBreakerRating $breakerRating,
        public RcdTripCurrent $rcdTripCurrent,
        public ?string $location,
        public readonly DateTimeImmutable $createdAt,
    ) {
    }

    /**
     * Warnung: der gewählte Leitungsschutzschalter übersteigt den
     * bauartbedingten Bemessungsstrom der Anschlussart. Wird für
     * Festanschluss (kein Bemessungsstrom, siehe PowerConnectionType)
     * bewusst übersprungen.
     */
    public function breakerExceedsRatedCurrent(): bool
    {
        $rated = $this->type->ratedCurrentAmps();
        if ($rated === null) {
            return false;
        }

        return $this->breakerRating->amps() > $rated;
    }

    /**
     * Hinweis: für diesen Anschluss wird ein eigener Stromverteiler mit
     * eigenem RCD benötigt. Trifft zu, wenn EINES der folgenden zutrifft:
     *   - es ist gar kein RCD verbaut, ODER
     *   - der verbaute RCD löst erst bei mehr als 30 mA aus, UND entweder
     *       - der Leitungsschutzschalter ist ≤ 32 A (für ≤32-A-Anschlüsse
     *         wird in der Praxis ein ≤30-mA-RCD verlangt), ODER
     *       - die Anschlussart ist Festanschluss: hier gibt es (anders als
     *         bei einem Steckverbinder mit bauartbedingtem Bemessungsstrom,
     *         siehe PowerConnectionType::ratedCurrentAmps()) keine fest
     *         vorgegebene Obergrenze für den Leitungsschutzschalter, daher
     *         wird die 30-mA-Pflicht hier unabhängig von dessen Größe
     *         angewendet (auf Nutzerwunsch, analog zur CEE-Warnung).
     */
    public function needsOwnDistributorWithRcd(): bool
    {
        if (!$this->rcdTripCurrent->isInstalled()) {
            return true;
        }

        $tripMa = $this->rcdTripCurrent->milliamps();
        if ($tripMa === null || $tripMa <= 30) {
            return false;
        }

        return $this->breakerRating->amps() <= 32 || $this->type === PowerConnectionType::Festanschluss;
    }

    /**
     * Aggregiert die Anschluss-Warnung auf Veranstaltungsort-Ebene: true,
     * sobald MINDESTENS ein Anschluss einen eigenen Verteiler mit RCD
     * benötigt.
     *
     * @param PowerConnection[] $connections
     */
    public static function anyNeedsDistributorWithRcd(array $connections): bool
    {
        foreach ($connections as $connection) {
            if ($connection->needsOwnDistributorWithRcd()) {
                return true;
            }
        }

        return false;
    }
}
