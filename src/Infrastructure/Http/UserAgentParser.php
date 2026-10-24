<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

/**
 * Sehr einfacher, bewusst best-effort User-Agent-Parser für die Anzeige unter
 * "Aktive Sitzungen" (siehe resources/views/profile/sessions.php) — KEINE
 * allgemeine UA-Parsing-Bibliothek, sondern eine Kette von str_contains()-
 * Prüfungen in sinnvoller Prioritätsreihenfolge. Reihenfolge ist wichtig:
 *   - Edge ("Edg/") enthält ebenfalls "Chrome" im UA-String, muss also VOR
 *     Chrome geprüft werden.
 *   - Chrome auf iOS heißt im UA-String "CriOS" statt "Chrome".
 *   - Safaris UA-String enthält ebenfalls "Safari", auch bei Chrome/Edge —
 *     Safari wird daher nur erkannt, wenn "Chrome"/"CriOS"/"Edg" ALLE fehlen.
 * Unbekannte/leere UA-Strings liefern einen generischen Platzhalter statt
 * eines falschen Ratens.
 */
final class UserAgentParser
{
    public static function describe(?string $userAgent): string
    {
        $ua = trim((string) $userAgent);

        if ($ua === '') {
            return 'Unbekanntes Gerät';
        }

        $browser = self::detectBrowser($ua);
        $os = self::detectOs($ua);

        if ($browser === null && $os === null) {
            // Nichts erkannt — gekürzten Rohwert als Fallback anzeigen, statt
            // einfach nur "unbekannt" (hilft bei ungewöhnlichen Clients/Bots
            // trotzdem beim Wiedererkennen der Sitzung).
            return mb_strlen($ua) > 60 ? mb_substr($ua, 0, 60) . '…' : $ua;
        }

        if ($browser !== null && $os !== null) {
            return "{$browser} unter {$os}";
        }

        return $browser ?? $os ?? 'Unbekanntes Gerät';
    }

    private static function detectBrowser(string $ua): ?string
    {
        if (str_contains($ua, 'Edg/') || str_contains($ua, 'EdgA/') || str_contains($ua, 'EdgiOS/')) {
            return 'Edge';
        }

        if (str_contains($ua, 'CriOS')) {
            return 'Chrome';
        }

        if (str_contains($ua, 'Chrome/') || str_contains($ua, 'Chromium/')) {
            return 'Chrome';
        }

        if (str_contains($ua, 'Firefox/') || str_contains($ua, 'FxiOS')) {
            return 'Firefox';
        }

        // Safari nur, wenn keiner der oben genannten Chromium-/Firefox-
        // basierten Browser zutrifft (deren UA enthält ebenfalls "Safari").
        if (str_contains($ua, 'Safari/') && str_contains($ua, 'Version/')) {
            return 'Safari';
        }

        return null;
    }

    private static function detectOs(string $ua): ?string
    {
        if (str_contains($ua, 'Windows')) {
            return 'Windows';
        }

        if (str_contains($ua, 'Android')) {
            return 'Android';
        }

        if (str_contains($ua, 'iPhone') || str_contains($ua, 'iPad') || str_contains($ua, 'iPod')) {
            return 'iOS';
        }

        if (str_contains($ua, 'Mac OS X') || str_contains($ua, 'Macintosh')) {
            return 'macOS';
        }

        if (str_contains($ua, 'Linux')) {
            return 'Linux';
        }

        return null;
    }
}
