<?php

declare(strict_types=1);

namespace App\Domain\Network;

/**
 * IPv4/IPv6 helpers working on packed binary addresses (inet_pton format),
 * so IPv6 arithmetic needs neither GMP nor BCMath (migration 155).
 */
final class IpAddress
{
    /** Private ranges: RFC 1918 for IPv4, Unique Local Addresses (RFC 4193) for IPv6. */
    public const PRIVATE_IPV4 = ['10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16'];
    public const PRIVATE_IPV6 = ['fc00::/7'];

    /** Packed address of the given version, or null if the text is no valid address of that version. */
    public static function parse(string $text, int $version): ?string
    {
        $text = trim($text);
        if ($text === '') {
            return null;
        }
        $flag = $version === 4 ? FILTER_FLAG_IPV4 : FILTER_FLAG_IPV6;
        if (filter_var($text, FILTER_VALIDATE_IP, $flag) === false) {
            return null;
        }
        $packed = @inet_pton($text);

        return $packed === false ? null : $packed;
    }

    /** Canonical text form, e.g. "fd00:10::1" (lowercase, shortened). */
    public static function format(string $packed): string
    {
        return (string) inet_ntop($packed);
    }

    /** Canonical text of an address of the given version, or null if invalid. */
    public static function normalize(string $text, int $version): ?string
    {
        $packed = self::parse($text, $version);

        return $packed === null ? null : self::format($packed);
    }

    public static function maxPrefix(int $version): int
    {
        return $version === 4 ? 32 : 128;
    }

    /** Packed netmask for the prefix length, e.g. 24 => ff ff ff 00. */
    public static function mask(int $prefix, int $version): string
    {
        $bytes = $version === 4 ? 4 : 16;
        $mask = '';
        for ($i = 0; $i < $bytes; $i++) {
            $bits = max(0, min(8, $prefix - $i * 8));
            $mask .= chr($bits === 0 ? 0 : (0xFF << (8 - $bits)) & 0xFF);
        }

        return $mask;
    }

    public static function networkAddress(string $packed, int $prefix): string
    {
        return $packed & self::mask($prefix, strlen($packed) === 4 ? 4 : 6);
    }

    /** Last address of the subnet (the broadcast address for IPv4). */
    public static function lastAddress(string $packed, int $prefix): string
    {
        return $packed | ~self::mask($prefix, strlen($packed) === 4 ? 4 : 6);
    }

    public static function contains(string $networkPacked, int $prefix, string $packed): bool
    {
        return strlen($networkPacked) === strlen($packed)
            && self::networkAddress($packed, $prefix) === self::networkAddress($networkPacked, $prefix);
    }

    /** Compares two packed addresses of the same version (<0, 0, >0). */
    public static function compare(string $a, string $b): int
    {
        return strcmp($a, $b) <=> 0;
    }

    /** Address + 1, or null on overflow. */
    public static function increment(string $packed): ?string
    {
        for ($i = strlen($packed) - 1; $i >= 0; $i--) {
            $byte = ord($packed[$i]);
            if ($byte < 0xFF) {
                $packed[$i] = chr($byte + 1);

                return $packed;
            }
            $packed[$i] = "\x00";
        }

        return null;
    }

    /** Address - 1, or null on underflow. */
    public static function decrement(string $packed): ?string
    {
        for ($i = strlen($packed) - 1; $i >= 0; $i--) {
            $byte = ord($packed[$i]);
            if ($byte > 0) {
                $packed[$i] = chr($byte - 1);

                return $packed;
            }
            $packed[$i] = "\xFF";
        }

        return null;
    }

    /** Dotted IPv4 subnet mask for a prefix length, e.g. 24 => "255.255.255.0". */
    public static function prefixToSubnetMask(int $prefix): string
    {
        return self::format(self::mask($prefix, 4));
    }

    /**
     * Prefix length from "24", "/24" or a dotted subnet mask like
     * "255.255.255.0" (IPv4 only). Null if invalid or non-contiguous.
     */
    public static function parsePrefix(string $text, int $version): ?int
    {
        $text = ltrim(trim($text), '/');
        if ($text === '') {
            return null;
        }
        if (preg_match('/^\d{1,3}$/', $text) === 1) {
            $prefix = (int) $text;

            return $prefix <= self::maxPrefix($version) ? $prefix : null;
        }
        if ($version !== 4) {
            return null;
        }
        $mask = self::parse($text, 4);
        if ($mask === null) {
            return null;
        }
        $bits = '';
        foreach (str_split($mask) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }
        if (preg_match('/^1*0*$/', $bits) !== 1) {
            return null;
        }

        return substr_count($bits, '1');
    }

    /** Whether the whole subnet lies inside one of the private ranges. */
    public static function isPrivateNetwork(string $networkPacked, int $prefix): bool
    {
        $version = strlen($networkPacked) === 4 ? 4 : 6;
        foreach ($version === 4 ? self::PRIVATE_IPV4 : self::PRIVATE_IPV6 as $range) {
            [$address, $rangePrefix] = explode('/', $range);
            $rangePacked = (string) inet_pton($address);
            if ($prefix >= (int) $rangePrefix && self::contains($rangePacked, (int) $rangePrefix, $networkPacked)) {
                return true;
            }
        }

        return false;
    }
}
