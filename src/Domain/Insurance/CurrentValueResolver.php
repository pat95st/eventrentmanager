<?php

declare(strict_types=1);

namespace App\Domain\Insurance;

use App\Domain\InventoryNumber\InventoryNumber;

/**
 * Ermittelt den in der Versicherungsliste ausgewiesenen Zeitwert eines
 * Exemplars. Reihenfolge (das Spezifischere gewinnt, analog zum
 * abweichenden Einkaufspreis einer Inventarnummer):
 *   1. eigener Zeitwert der Inventarnummer (händisch oder per KI)
 *   2. fester Zeitwert des Artikels (z. B. XLR-Kabel)
 *   3. kein Wert → Feld bleibt leer
 */
final class CurrentValueResolver
{
    public const ORIGIN_ITEM = 'item';
    public const ORIGIN_AI = 'ai';
    public const ORIGIN_ARTICLE = 'article';

    /**
     * @param InventoryNumber|null $inventoryNumber null für noch nicht ausgegebene (nur geplante) Exemplare
     * @return array{value: string|null, origin: string|null}
     */
    public static function resolve(?InventoryNumber $inventoryNumber, ?string $articleFixedValue): array
    {
        if ($inventoryNumber?->currentValue !== null) {
            return ['value' => $inventoryNumber->currentValue, 'origin' => $inventoryNumber->isCurrentValueAiEstimate() ? self::ORIGIN_AI : self::ORIGIN_ITEM];
        }
        if ($articleFixedValue !== null) {
            return ['value' => $articleFixedValue, 'origin' => self::ORIGIN_ARTICLE];
        }

        return ['value' => null, 'origin' => null];
    }

    /**
     * Normalisiert eine Benutzereingabe („1.234,50 €“, „12,5“, „99.9“) zu
     * "1234.50"; leer → null.
     *
     * @throws \InvalidArgumentException bei ungültiger Eingabe
     */
    public static function normalizeAmount(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        $value = str_replace([' ', "\u{00A0}", '€', 'EUR'], '', $value);
        if (str_contains($value, ',')) {
            $value = str_replace(['.', ','], ['', '.'], $value);
        }
        if (!is_numeric($value) || (float) $value < 0 || (float) $value > 99999999) {
            throw new \InvalidArgumentException('Ungültiger Betrag.');
        }

        return number_format((float) $value, 2, '.', '');
    }
}
