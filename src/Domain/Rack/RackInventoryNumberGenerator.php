<?php

declare(strict_types=1);

namespace App\Domain\Rack;

/**
 * Inventurnummern für Rack-Exemplare wie bei Artikeln und Cases:
 * (Hersteller-Kürzel)-(Rack-Kürzel)-(laufende Nummer je Rack-Typ), z. B.
 * "THO-RK10-0001" (Migration 124).
 */
final class RackInventoryNumberGenerator
{
    public static function buildPrefix(string $manufacturerPrefix, string $rackPrefix): string
    {
        return sprintf('%s-%s-', $manufacturerPrefix, $rackPrefix);
    }

    /** @param RackExemplar[] $existing */
    public static function nextNumber(array $existing, string $manufacturerPrefix, string $rackPrefix): string
    {
        $prefix = self::buildPrefix($manufacturerPrefix, $rackPrefix);
        $max = 0;
        foreach ($existing as $exemplar) {
            if (!str_starts_with($exemplar->inventoryNumber, $prefix)) {
                continue;
            }
            $suffix = substr($exemplar->inventoryNumber, strlen($prefix));
            if (preg_match('/^\d+$/', $suffix) === 1) {
                $max = max($max, (int) $suffix);
            }
        }

        return $prefix . sprintf('%04d', $max + 1);
    }
}
