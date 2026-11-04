<?php

declare(strict_types=1);

namespace App\Domain\CaseInventoryNumber;

/**
 * Bildet standardisierte Inventurnummern für Case-Exemplare aus Hersteller-/
 * Case-Kürzel und einer je Case-Modell fortlaufenden Nummer:
 * (Hersteller-Kürzel)-(Case-Kürzel)-(Nummer), z. B. "BOS-RCK-0001".
 * Zusätzlich hat jedes Exemplar eine fortlaufende Case-Nummer (CASE-0001 …,
 * Migration 124), siehe AddCaseInventoryNumberHandler.
 */
final class CaseInventoryNumberGenerator
{
    public static function buildPrefix(string $manufacturerPrefix, string $casePrefix): string
    {
        return sprintf('%s-%s-', $manufacturerPrefix, $casePrefix);
    }

    /** @param CaseInventoryNumber[] $existing */
    public static function nextNumber(array $existing, string $manufacturerPrefix, string $casePrefix): string
    {
        $prefix = self::buildPrefix($manufacturerPrefix, $casePrefix);
        $max = 0;
        foreach ($existing as $caseInventoryNumber) {
            if (!str_starts_with($caseInventoryNumber->number, $prefix)) {
                continue;
            }
            $suffix = substr($caseInventoryNumber->number, strlen($prefix));
            if (preg_match('/^\d+$/', $suffix) === 1) {
                $max = max($max, (int) $suffix);
            }
        }

        return $prefix . sprintf('%04d', $max + 1);
    }
}
