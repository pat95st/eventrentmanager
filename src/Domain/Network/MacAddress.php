<?php

declare(strict_types=1);

namespace App\Domain\Network;

/** MAC address normalization (migration 155). */
final class MacAddress
{
    /**
     * Accepts "aa:bb:cc:dd:ee:ff", "AA-BB-CC-DD-EE-FF", "aabb.ccdd.eeff" or
     * "aabbccddeeff" and returns "AA:BB:CC:DD:EE:FF", or null if invalid.
     */
    public static function normalize(string $text): ?string
    {
        $hex = preg_replace('/[\s:.\-]/', '', $text) ?? '';
        if (preg_match('/^[0-9A-Fa-f]{12}$/', $hex) !== 1) {
            return null;
        }

        return implode(':', str_split(strtoupper($hex), 2));
    }
}
