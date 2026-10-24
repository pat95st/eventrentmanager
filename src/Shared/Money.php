<?php

declare(strict_types=1);

namespace App\Shared;

/**
 * Normalisierung und Validierung von Geldbeträgen (Einkaufspreise), die als
 * String geführt werden, um Rundungsfehler bei DECIMAL zu vermeiden (analog
 * zu Article::$weightValue). Akzeptiert sowohl Punkt als auch Komma als
 * Dezimaltrennzeichen (deutsche Eingabegewohnheit), speichert aber immer mit
 * Punkt und genau zwei Nachkommastellen.
 */
final class Money
{
    /**
     * Liefert den normalisierten Betrag ("12.50") oder null bei leerer
     * Eingabe. Liefert false, wenn die Eingabe kein gültiger, nicht-negativer
     * Betrag ist.
     */
    public static function normalize(?string $raw): string|false|null
    {
        if ($raw === null) {
            return null;
        }

        $trimmed = trim($raw);
        if ($trimmed === '') {
            return null;
        }

        $normalized = str_replace(',', '.', $trimmed);

        if (preg_match('/^\d+(\.\d{1,2})?$/', $normalized) !== 1) {
            return false;
        }

        return number_format((float) $normalized, 2, '.', '');
    }
}
