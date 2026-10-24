<?php

declare(strict_types=1);

namespace App\Domain\Article;

/**
 * Rechnet Article::$weightValue/$weightUnit ('g'|'kg'|null) in ein
 * einheitliches Gewicht in kg um, z. B. für die Überladungs-Berechnung der
 * Beladungsliste (siehe App\Domain\Vehicle\VehicleLoadCalculator). Liefert
 * null, wenn kein Gewicht hinterlegt ist oder der Wert nicht numerisch ist
 * — ein unbekanntes Gewicht darf NICHT als 0 kg gewertet werden, da sonst
 * eine Überladung fälschlich verneint würde.
 */
final class WeightConverter
{
    public static function toKg(?string $weightValue, ?string $weightUnit): ?float
    {
        if ($weightValue === null || trim($weightValue) === '') {
            return null;
        }

        $normalized = str_replace(',', '.', trim($weightValue));
        if (!is_numeric($normalized)) {
            return null;
        }

        $value = (float) $normalized;

        return match ($weightUnit) {
            'g' => $value / 1000,
            'kg' => $value,
            default => null, // unbekannte/fehlende Einheit -> Gewicht nicht verwertbar
        };
    }
}
