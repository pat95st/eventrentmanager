<?php

declare(strict_types=1);

namespace App\Shared;

/**
 * Normalisierung und Validierung von Hersteller-/Artikel-Präfixen, aus
 * denen zusammen mit einer laufenden Nummer die Inventurnummern gebildet
 * werden (Format: HERSTELLER-ARTIKEL-0001).
 */
final class Prefix
{
    /** Trimmt und schreibt das Präfix in Großbuchstaben, wie es gespeichert/verglichen wird. */
    public static function normalize(string $raw): string
    {
        return strtoupper(trim($raw));
    }

    /** Nur Großbuchstaben und Ziffern, 1 bis 10 Zeichen lang. */
    public static function isValid(string $normalized): bool
    {
        return preg_match('/^[A-Z0-9]{1,10}$/', $normalized) === 1;
    }
}
