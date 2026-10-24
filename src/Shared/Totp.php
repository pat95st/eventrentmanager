<?php

declare(strict_types=1);

namespace App\Shared;

/**
 * Minimale, abhängigkeitsfreie TOTP-Implementierung nach RFC 6238 (auf
 * Basis von HOTP, RFC 4226): 6-stellige Codes, 30-Sekunden-Zeitfenster,
 * HMAC-SHA1 — exakt das, was Google Authenticator, Microsoft Authenticator,
 * Authy & Co. standardmäßig erwarten. Bewusst ohne externe Bibliothek
 * (Composer-Pakete sind in dieser Umgebung nicht zuverlässig installierbar,
 * siehe README/Projektdoku), stattdessen die Standard-Algorithmen selbst
 * umgesetzt — RFC 6238/4226 sind kurz und stabil genug, um sie ohne
 * Bibliothek korrekt zu implementieren.
 *
 * Auf einen QR-Code beim Einrichten wird bewusst verzichtet (würde eine
 * weitere Bibliothek oder eine CDN-Abhängigkeit erfordern) — stattdessen
 * wird das Secret als Text zur manuellen Eingabe in der Authenticator-App
 * angezeigt (siehe resources/views/profile/security.php).
 */
final class Totp
{
    // Bewusst ohne Typangabe an der Konstante (typed class constants
    // benötigen PHP 8.3+, das Projekt zielt aber auf PHP >= 8.1 ab).
    private const TIME_STEP = 30;
    private const DIGITS = 6;
    private const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /** Erzeugt ein neues, zufälliges Base32-Secret (160 Bit, wie von den meisten Apps erwartet). */
    public static function generateSecret(): string
    {
        return self::base32Encode(random_bytes(20));
    }

    /** Aktuellen 6-stelligen Code für ein Secret berechnen (für die eigene Anzeige/Tests). */
    public static function currentCode(string $base32Secret, ?int $timestamp = null): string
    {
        $timestamp ??= time();
        $counter = intdiv($timestamp, self::TIME_STEP);

        return self::hotp($base32Secret, $counter);
    }

    /**
     * Prüft einen eingegebenen Code gegen das Secret. Erlaubt ein Zeitfenster
     * von ±1 Schritt (also bis zu 30s Uhrenabweichung in beide Richtungen),
     * wie in den meisten TOTP-Implementierungen üblich.
     */
    public static function verify(string $base32Secret, string $code, ?int $timestamp = null): bool
    {
        $code = trim($code);
        if (!preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        $timestamp ??= time();
        $counter = intdiv($timestamp, self::TIME_STEP);

        foreach ([-1, 0, 1] as $drift) {
            if (hash_equals(self::hotp($base32Secret, $counter + $drift), $code)) {
                return true;
            }
        }

        return false;
    }

    /** otpauth://-URI für den Fall, dass eine App das Secret später doch per QR-Code einliest. */
    public static function provisioningUri(string $base32Secret, string $accountEmail, string $issuer = 'Materialverleih'): string
    {
        $label = rawurlencode($issuer . ':' . $accountEmail);

        return sprintf(
            'otpauth://totp/%s?secret=%s&issuer=%s&algorithm=SHA1&digits=%d&period=%d',
            $label,
            $base32Secret,
            rawurlencode($issuer),
            self::DIGITS,
            self::TIME_STEP,
        );
    }

    private static function hotp(string $base32Secret, int $counter): string
    {
        $key = self::base32Decode($base32Secret);
        $binCounter = pack('N*', 0, $counter); // 8-Byte Big-Endian-Zähler

        $hash = hash_hmac('sha1', $binCounter, $key, true);
        $offset = ord($hash[19]) & 0xf;

        $truncated = ((ord($hash[$offset]) & 0x7f) << 24)
            | ((ord($hash[$offset + 1]) & 0xff) << 16)
            | ((ord($hash[$offset + 2]) & 0xff) << 8)
            | (ord($hash[$offset + 3]) & 0xff);

        $otp = $truncated % (10 ** self::DIGITS);

        return str_pad((string) $otp, self::DIGITS, '0', STR_PAD_LEFT);
    }

    private static function base32Encode(string $binary): string
    {
        $bits = '';
        foreach (str_split($binary) as $char) {
            $bits .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }

        $output = '';
        foreach (str_split($bits, 5) as $chunk) {
            if (strlen($chunk) < 5) {
                $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
            }
            $output .= self::BASE32_ALPHABET[bindec($chunk)];
        }

        return $output;
    }

    private static function base32Decode(string $base32): string
    {
        $base32 = strtoupper(str_replace(['=', ' '], '', $base32));

        $bits = '';
        foreach (str_split($base32) as $char) {
            $index = strpos(self::BASE32_ALPHABET, $char);
            if ($index === false) {
                continue;
            }
            $bits .= str_pad(decbin($index), 5, '0', STR_PAD_LEFT);
        }

        $binary = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) < 8) {
                continue; // Restbits am Ende (kein volles Byte) werden verworfen.
            }
            $binary .= chr(bindec($byte));
        }

        return $binary;
    }
}
