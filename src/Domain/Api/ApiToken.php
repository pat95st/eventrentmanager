<?php

declare(strict_types=1);

namespace App\Domain\Api;

use DateTimeImmutable;

/**
 * Ein von einem Administrator ausgestelltes API-Token für die optionale
 * REST-API (/api/v1). Es wird — wie bei PasswordSetupToken — nur der Hash des
 * Klartext-Tokens gespeichert ($tokenHash); der Klartext wird ausschließlich
 * einmalig direkt nach dem Anlegen angezeigt und danach nie wieder
 * rekonstruierbar. $tokenLastFour hält die letzten vier Zeichen des
 * Klartexts, damit ein Token in der Liste wiedererkannt werden kann, ohne
 * dass der volle Wert erneut sichtbar sein muss (analog zu "Kreditkarte endet
 * auf …").
 */
final class ApiToken
{
    /**
     * @param list<ApiScope> $scopes granted scopes (migration 175)
     */
    public function __construct(
        public readonly string $id,
        public string $name,
        public readonly string $tokenHash,
        public readonly string $tokenLastFour,
        // Erlaubte Client-IPs/-Netze, eine pro Zeile (einzelne IPv4-/IPv6-Adresse
        // oder CIDR-Notation wie "203.0.113.0/24"). NULL/leer = von überall
        // erlaubt (siehe IpAllowlist::matches()).
        public ?string $ipAllowlist,
        public bool $enabled,
        public ?DateTimeImmutable $lastUsedAt,
        public readonly DateTimeImmutable $createdAt,
        public array $scopes = [],
        // Read-only tokens are denied every write scope, whatever is ticked.
        public bool $readOnly = false,
        // Requests per minute for this token; null = global default from ApiSettings.
        public ?int $rateLimitPerMinute = null,
        // Released for the MCP server /mcp (migration 183); off by default so an
        // existing integration token does not become usable by an LLM client.
        public bool $mcpAllowed = false,
    ) {
    }

    public function allows(ApiScope $scope): bool
    {
        if ($this->readOnly && !$scope->isRead()) {
            return false;
        }

        return in_array($scope, $this->scopes, true);
    }

    /** @return list<string> */
    public function scopeValues(): array
    {
        return array_map(static fn (ApiScope $s) => $s->value, $this->scopes);
    }
}
