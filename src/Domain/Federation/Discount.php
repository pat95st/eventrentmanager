<?php

declare(strict_types=1);

namespace App\Domain\Federation;

use App\Domain\Exception\ValidationException;

/** Parsing and validation of discount percentages (0–100, two decimals). */
final class Discount
{
    public static function parse(string|float|int|null $value, string $field): float
    {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            return 0.0;
        }
        $normalized = is_string($value) ? str_replace(',', '.', trim($value)) : (string) $value;
        if (!is_numeric($normalized)) {
            throw new ValidationException([$field => 'Bitte einen Rabatt zwischen 0 und 100 % angeben.']);
        }
        $percent = round((float) $normalized, 2);
        if ($percent < 0 || $percent > 100) {
            throw new ValidationException([$field => 'Bitte einen Rabatt zwischen 0 und 100 % angeben.']);
        }

        return $percent;
    }

    public static function equals(float $a, float $b): bool
    {
        return abs($a - $b) < 0.005;
    }

    public static function format(float $percent): string
    {
        return rtrim(rtrim(number_format($percent, 2, ',', ''), '0'), ',') . ' %';
    }

    /** Price after applying $percent, rounded to cents. */
    public static function apply(float $price, float $percent): float
    {
        return round($price * (100 - $percent) / 100, 2);
    }
}
