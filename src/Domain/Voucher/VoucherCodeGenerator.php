<?php

declare(strict_types=1);

namespace App\Domain\Voucher;

/**
 * Erzeugt zufällige, gut lesbare Gutscheincodes: 10 Zeichen aus einem
 * Alphabet ohne die leicht verwechselbaren Zeichen 0/O und 1/I, optional
 * mit einem frei wählbaren, vorangestellten Präfix (z. B. "SOMMER-A1B2C3D4E5").
 * Rein pure Funktion ohne DB-Zugriff — die Eindeutigkeit wird vom
 * aufrufenden Handler durchgesetzt (siehe CreateVoucherHandler), analog zu
 * App\Domain\InventoryNumber\InventoryNumberGenerator.
 */
final class VoucherCodeGenerator
{
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    private const RANDOM_LENGTH = 10;

    public static function generate(?string $prefix = null): string
    {
        $random = '';
        for ($i = 0; $i < self::RANDOM_LENGTH; $i++) {
            $random .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        $normalizedPrefix = $prefix !== null ? strtoupper(trim($prefix)) : '';

        return $normalizedPrefix !== '' ? $normalizedPrefix . '-' . $random : $random;
    }
}
