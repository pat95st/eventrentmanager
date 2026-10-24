<?php

declare(strict_types=1);

namespace App\Domain\Api;

/**
 * Prüft eine Client-IP gegen die newline-/kommagetrennte Allowlist eines
 * ApiToken (einzelne Adressen oder CIDR-Notation, IPv4 und IPv6). Reine
 * Werkzeug-Klasse ohne Abhängigkeiten — die Anwendung hat keine
 * IP-Range-Bibliothek, daher wird der CIDR-Vergleich hier bitweise über
 * inet_pton() selbst gemacht.
 */
final class IpAllowlist
{
    /** Leere/NULL-Allowlist bedeutet "von überall erlaubt". */
    public static function matches(string $clientIp, ?string $allowlist): bool
    {
        if ($allowlist === null || trim($allowlist) === '') {
            return true;
        }

        foreach (self::entries($allowlist) as $entry) {
            if (self::matchesEntry($clientIp, $entry)) {
                return true;
            }
        }

        return false;
    }

    /** @return string[] */
    public static function entries(string $allowlist): array
    {
        $parts = preg_split('/[\r\n,]+/', $allowlist, -1, PREG_SPLIT_NO_EMPTY);
        if ($parts === false) {
            return [];
        }

        return array_values(array_filter(array_map('trim', $parts), static fn ($entry) => $entry !== ''));
    }

    /** Liefert eine Fehlermeldung für den ersten ungültigen Eintrag, oder null, wenn alle Einträge gültige IPs/CIDR-Netze sind. */
    public static function validationError(string $allowlist): ?string
    {
        foreach (self::entries($allowlist) as $entry) {
            if (!self::isValidEntry($entry)) {
                return "\"{$entry}\" ist keine gültige IP-Adresse oder CIDR-Notation (z. B. 203.0.113.5 oder 203.0.113.0/24).";
            }
        }

        return null;
    }

    private static function isValidEntry(string $entry): bool
    {
        if (!str_contains($entry, '/')) {
            return @inet_pton($entry) !== false;
        }

        $parts = explode('/', $entry, 2);
        if (count($parts) !== 2 || !ctype_digit($parts[1])) {
            return false;
        }
        $subnetBin = @inet_pton($parts[0]);
        if ($subnetBin === false) {
            return false;
        }

        return (int) $parts[1] <= strlen($subnetBin) * 8;
    }

    private static function matchesEntry(string $clientIp, string $entry): bool
    {
        if (!str_contains($entry, '/')) {
            return $entry === $clientIp;
        }

        return self::cidrMatches($clientIp, $entry);
    }

    private static function cidrMatches(string $clientIp, string $cidr): bool
    {
        $parts = explode('/', $cidr, 2);
        if (count($parts) !== 2) {
            return false;
        }
        [$subnet, $maskStr] = $parts;
        if (!ctype_digit($maskStr)) {
            return false;
        }
        $mask = (int) $maskStr;

        $clientBin = @inet_pton($clientIp);
        $subnetBin = @inet_pton($subnet);
        if ($clientBin === false || $subnetBin === false || strlen($clientBin) !== strlen($subnetBin)) {
            return false;
        }
        if ($mask < 0 || $mask > strlen($clientBin) * 8) {
            return false;
        }

        $fullBytes = intdiv($mask, 8);
        $remainderBits = $mask % 8;

        if ($fullBytes > 0 && substr($clientBin, 0, $fullBytes) !== substr($subnetBin, 0, $fullBytes)) {
            return false;
        }

        if ($remainderBits > 0) {
            $maskByte = (0xFF << (8 - $remainderBits)) & 0xFF;
            $clientByte = ord($clientBin[$fullBytes]);
            $subnetByte = ord($subnetBin[$fullBytes]);
            if (($clientByte & $maskByte) !== ($subnetByte & $maskByte)) {
                return false;
            }
        }

        return true;
    }
}
