<?php

declare(strict_types=1);

namespace App\Domain\Vehicle;

/**
 * Reine Berechnungslogik für die Beladungsliste einer Veranstaltung:
 * Gesamtgewicht der in einem Fahrzeug verladenen Posten, Überladungs-Status
 * gegen die Zuladung (payloadKg) dieses Fahrzeugs, sowie die
 * Anhängelast-Prüfung eines gezogenen Anhängers gegen die Anhängelast des
 * Zugfahrzeugs.
 *
 * Alle Methoden liefern bewusst ein nullable bool/float statt false/0.0,
 * wenn ein benötigter Wert fehlt (z. B. kein Gewicht am Artikel hinterlegt,
 * keine Zuladung am Fahrzeug gepflegt) — ein unbekannter Zustand darf nicht
 * mit "nicht überladen" verwechselt werden (falsches Sicherheitsgefühl).
 */
final class VehicleLoadCalculator
{
    /**
     * Summiert die Gewichte (in kg) aller in einem Fahrzeug verladenen
     * Posten. Ist auch nur ein Gewicht unbekannt (null in $itemWeightsKg),
     * ist das Gesamtgewicht ebenfalls unbekannt (null) statt die fehlende
     * Angabe stillschweigend als 0 kg zu werten.
     *
     * @param array<float|null> $itemWeightsKg
     */
    public static function totalLoadedWeightKg(array $itemWeightsKg): ?float
    {
        if ($itemWeightsKg === []) {
            return 0.0;
        }

        foreach ($itemWeightsKg as $weight) {
            if ($weight === null) {
                return null;
            }
        }

        return array_sum($itemWeightsKg);
    }

    /**
     * Ist ein Fahrzeug anhand seiner Zuladung überladen? null = unbekannt
     * (keine Zuladung am Fahrzeug hinterlegt oder Gesamtgewicht unbekannt).
     */
    public static function isOverloaded(?float $payloadKg, ?float $totalLoadedWeightKg): ?bool
    {
        if ($payloadKg === null || $totalLoadedWeightKg === null) {
            return null;
        }

        return $totalLoadedWeightKg > $payloadKg;
    }

    /**
     * Wie viel Zuladung bleibt noch übrig (negativ = Überladung um diesen
     * Betrag). null = unbekannt, siehe isOverloaded().
     */
    public static function remainingPayloadKg(?float $payloadKg, ?float $totalLoadedWeightKg): ?float
    {
        if ($payloadKg === null || $totalLoadedWeightKg === null) {
            return null;
        }

        return $payloadKg - $totalLoadedWeightKg;
    }

    /**
     * Anhängelast-Prüfung: wird die Anhängelast des Zugfahrzeugs (Auto)
     * überschritten, wenn der Anhänger (Leergewicht + eigene Beladung) von
     * ihm gezogen wird? null = unbekannt (fehlende Anhängelast, fehlendes
     * Leergewicht oder unbekanntes Beladungsgewicht des Anhängers).
     */
    public static function isTowOverloaded(?float $towCapacityKg, ?float $trailerEmptyWeightKg, ?float $trailerLoadedWeightKg): ?bool
    {
        if ($towCapacityKg === null || $trailerEmptyWeightKg === null || $trailerLoadedWeightKg === null) {
            return null;
        }

        return ($trailerEmptyWeightKg + $trailerLoadedWeightKg) > $towCapacityKg;
    }

    /**
     * Gesamtgewicht des Anhängers inklusive Zuladung (Leergewicht + Fracht),
     * wie es auf die Anhängelast des Zugfahrzeugs angerechnet wird. null =
     * unbekannt.
     */
    public static function trailerTotalWeightKg(?float $trailerEmptyWeightKg, ?float $trailerLoadedWeightKg): ?float
    {
        if ($trailerEmptyWeightKg === null || $trailerLoadedWeightKg === null) {
            return null;
        }

        return $trailerEmptyWeightKg + $trailerLoadedWeightKg;
    }
}
