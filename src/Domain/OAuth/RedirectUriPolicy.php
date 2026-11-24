<?php

declare(strict_types=1);

namespace App\Domain\OAuth;

/**
 * Which redirect URIs a client may register: https URLs, or http only for
 * loopback addresses (native/CLI clients such as Claude Code). No fragments,
 * no credentials in the URL (OAuth 2.1, RFC 8252).
 */
final class RedirectUriPolicy
{
    public const MAX_LENGTH = 2000;

    /** Returns an error message, or null when the URI is acceptable. */
    public static function validationError(string $uri): ?string
    {
        if ($uri === '' || strlen($uri) > self::MAX_LENGTH) {
            return 'redirect_uri is empty or too long.';
        }
        $parts = parse_url($uri);
        if ($parts === false || !isset($parts['scheme'], $parts['host'])) {
            return 'redirect_uri "' . $uri . '" is not an absolute URL.';
        }
        if (isset($parts['fragment'])) {
            return 'redirect_uri must not contain a fragment.';
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            return 'redirect_uri must not contain credentials.';
        }
        $scheme = strtolower($parts['scheme']);
        if ($scheme === 'https') {
            return null;
        }
        if ($scheme === 'http' && self::isLoopback($uri)) {
            return null;
        }

        return 'redirect_uri must use https (http is only allowed for localhost / 127.0.0.1).';
    }

    public static function isLoopback(string $uri): bool
    {
        $parts = parse_url($uri);
        if ($parts === false || strtolower($parts['scheme'] ?? '') !== 'http') {
            return false;
        }

        return in_array(strtolower(trim($parts['host'] ?? '', '[]')), ['localhost', '127.0.0.1', '::1'], true);
    }

    public static function withoutPort(string $uri): string
    {
        return (string) preg_replace('#^(http://(?:\[[^\]]+\]|[^/:?]+)):\d+#i', '$1', $uri);
    }
}
