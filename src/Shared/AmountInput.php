<?php

declare(strict_types=1);

namespace App\Shared;

/**
 * Liest einen von Hand eingegebenen Euro-Betrag in deutscher Schreibweise:
 * „1.234,50“, „1234,5“, „25.000“ (Tausenderpunkte) und „99.90“ (Dezimalpunkt)
 * werden zu "1234.50", "25000.00" bzw. "99.90". Ein Punkt gilt nur dann als
 * Tausendertrennzeichen, wenn ihm jeweils genau drei Ziffern folgen.
 */
final class AmountInput
{
    /**
     * @return string|null normalisierter Betrag mit zwei Nachkommastellen; null bei leerer Eingabe
     * @throws \InvalidArgumentException bei ungültiger oder negativer Eingabe
     */
    public static function normalize(?string $value, float $max = 9999999999.99): ?string
    {
        $value = str_replace([' ', "\u{00A0}", '€', 'EUR'], '', trim((string) $value));
        if ($value === '') {
            return null;
        }
        if (str_contains($value, ',')) {
            $value = str_replace(['.', ','], ['', '.'], $value);
        } elseif (preg_match('/^\d{1,3}(\.\d{3})+$/', $value) === 1) {
            $value = str_replace('.', '', $value);
        }
        if (preg_match('/^\d+(\.\d+)?$/', $value) !== 1 || (float) $value > $max) {
            throw new \InvalidArgumentException('Ungültiger Betrag.');
        }

        return number_format((float) $value, 2, '.', '');
    }
}
