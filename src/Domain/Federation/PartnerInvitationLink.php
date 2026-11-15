<?php

declare(strict_types=1);

namespace App\Domain\Federation;

use App\Domain\Exception\ValidationException;

/**
 * Builds and parses invitation links of the form
 * "https://verleih.example.de/api/federation/v1/invitations/<code>".
 */
final class PartnerInvitationLink
{
    public const PATH = '/api/federation/v1/invitations/';

    public static function build(string $baseUrl, string $code): string
    {
        return self::normalizeBaseUrl($baseUrl) . self::PATH . $code;
    }

    /** @return array{baseUrl: string, code: string} */
    public static function parse(string $link): array
    {
        $link = trim($link);
        $position = strpos($link, self::PATH);
        if ($position === false) {
            throw new ValidationException(['link' => 'Das ist kein gültiger Einladungslink. Er endet auf „/api/federation/v1/invitations/…“.']);
        }
        $code = trim(substr($link, $position + strlen(self::PATH)), "/ \t\n\r");
        if (preg_match('/^[A-Za-z0-9_-]{32,128}$/', $code) !== 1) {
            throw new ValidationException(['link' => 'Der Einladungslink ist unvollständig.']);
        }

        return ['baseUrl' => self::normalizeBaseUrl(substr($link, 0, $position)), 'code' => $code];
    }

    /** Validates and normalizes a base URL ("https://host[:port][/path]", no trailing slash). */
    public static function normalizeBaseUrl(string $baseUrl): string
    {
        $baseUrl = rtrim(trim($baseUrl), '/');
        $parts = parse_url($baseUrl);
        if (
            $parts === false || !isset($parts['scheme'], $parts['host'])
            || !in_array(strtolower($parts['scheme']), ['http', 'https'], true)
            || isset($parts['query']) || isset($parts['fragment']) || isset($parts['user'])
        ) {
            throw new ValidationException(['link' => 'Die Adresse der Partner-Instanz ist ungültig (erwartet: https://…).']);
        }

        return strtolower($parts['scheme']) . '://' . strtolower($parts['host'])
            . (isset($parts['port']) ? ':' . $parts['port'] : '')
            . rtrim($parts['path'] ?? '', '/');
    }
}
