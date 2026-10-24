<?php

declare(strict_types=1);

namespace App\Domain\InventoryNumber;

/**
 * Bildet standardisierte Inventurnummern aus Hersteller-/Artikel-Präfix und
 * einer je Artikel fortlaufenden Nummer: (Hersteller-Präfix)-(Artikel-Präfix)-(Nummer),
 * z. B. "BOS-AKS-0001".
 */
final class InventoryNumberGenerator
{
    public static function buildPrefix(string $manufacturerPrefix, string $articlePrefix): string
    {
        return sprintf('%s-%s-', $manufacturerPrefix, $articlePrefix);
    }

    /**
     * Ermittelt die nächste freie laufende Nummer anhand der bereits vorhandenen
     * Inventurnummern des Artikels, die dem aktuellen Präfix entsprechen. Ältere
     * Inventurnummern in einem anderen Format werden dabei ignoriert.
     *
     * @param InventoryNumber[] $existing
     */
    public static function nextNumber(array $existing, string $manufacturerPrefix, string $articlePrefix): string
    {
        $prefix = self::buildPrefix($manufacturerPrefix, $articlePrefix);
        $max = 0;

        foreach ($existing as $inventoryNumber) {
            if (!str_starts_with($inventoryNumber->number, $prefix)) {
                continue;
            }

            $suffix = substr($inventoryNumber->number, strlen($prefix));
            if (preg_match('/^\d+$/', $suffix) === 1) {
                $max = max($max, (int) $suffix);
            }
        }

        return $prefix . sprintf('%04d', $max + 1);
    }
}
