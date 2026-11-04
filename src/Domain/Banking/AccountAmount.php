<?php

declare(strict_types=1);

namespace App\Domain\Banking;

/**
 * Beträge der Kontoführung in Cent (int), damit Salden ohne
 * Rundungsfehler summiert werden.
 */
final class AccountAmount
{
    /**
     * Eingabe wie "1.234,56", "1234.56", "12,5" oder "-3,20" in Cent, null
     * bei ungültiger Eingabe.
     */
    public static function parseCents(?string $raw): ?int
    {
        $value = str_replace([' ', "\u{00A0}", '€'], '', trim((string) $raw));
        if ($value === '') {
            return null;
        }
        $negative = str_starts_with($value, '-');
        $value = ltrim($value, '+-');
        $lastComma = strrpos($value, ',');
        $lastDot = strrpos($value, '.');
        if ($lastComma !== false && $lastDot !== false) {
            $decimal = $lastComma > $lastDot ? ',' : '.';
            $thousands = $decimal === ',' ? '.' : ',';
            $value = str_replace($thousands, '', $value);
            $value = str_replace($decimal, '.', $value);
        } elseif ($lastComma !== false) {
            $value = str_replace(',', '.', $value);
        } elseif ($lastDot !== false && preg_match('/^\d{1,3}(\.\d{3})+$/', $value) === 1) {
            $value = str_replace('.', '', $value);
        }
        if (preg_match('/^\d+(\.\d{1,2})?$/', $value) !== 1) {
            return null;
        }
        [$euros, $cents] = array_pad(explode('.', $value, 2), 2, '0');
        $total = (int) $euros * 100 + (int) str_pad($cents, 2, '0');

        return $negative ? -$total : $total;
    }

    /** Cent aus einem DECIMAL-String der Datenbank ("-12.30"). */
    public static function fromDecimal(string $decimal): int
    {
        return self::parseCents($decimal) ?? 0;
    }

    /** Cent als DECIMAL-String für die Datenbank. */
    public static function toDecimal(int $cents): string
    {
        $sign = $cents < 0 ? '-' : '';
        $abs = abs($cents);

        return $sign . intdiv($abs, 100) . '.' . str_pad((string) ($abs % 100), 2, '0', STR_PAD_LEFT);
    }

    /** Anzeige "1.234,56 €" (mit Vorzeichen bei negativen Beträgen). */
    public static function format(int $cents, bool $withSign = false): string
    {
        $sign = $cents < 0 ? '−' : ($withSign && $cents > 0 ? '+' : '');

        return $sign . number_format(abs($cents) / 100, 2, ',', '.') . ' €';
    }
}
