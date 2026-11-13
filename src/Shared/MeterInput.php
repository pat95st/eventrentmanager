<?php

declare(strict_types=1);

namespace App\Shared;

/**
 * Liest eine von Hand eingegebene Länge in Metern: "8,5", "8.5" und "8,50 m"
 * werden zu 8.5. Höchstens zwei Nachkommastellen (= Zentimeter).
 */
final class MeterInput
{
    /** @return float|null null bei leerer, negativer oder sonst ungültiger Eingabe */
    public static function parse(string $value): ?float
    {
        $value = str_replace([' ', "\u{00A0}"], '', trim($value));
        $value = (string) preg_replace('/m$/i', '', $value);
        $value = str_replace(',', '.', $value);

        if (preg_match('/^\d{1,4}(\.\d{1,2})?$/', $value) !== 1) {
            return null;
        }

        return (float) $value;
    }
}
