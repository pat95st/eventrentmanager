<?php

declare(strict_types=1);

namespace App\Domain\OAuth;

use App\Domain\Api\ApiScope;
use DateTimeImmutable;

/**
 * Connection of one user with one OAuth client ("verbundener KI-Assistent").
 * Holds the granted scopes and the current refresh token (hash only); the
 * access token is an ApiToken linked via ApiToken::$oauthGrantId. Deleting
 * the grant disconnects the client (the access token goes with it).
 */
final class OAuthGrant
{
    /** @param list<ApiScope> $scopes */
    public function __construct(
        public readonly string $id,
        public readonly string $clientId,
        public readonly string $userId,
        public array $scopes,
        public string $refreshTokenHash,
        public ?string $previousRefreshTokenHash,
        public DateTimeImmutable $refreshExpiresAt,
        public readonly DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $lastUsedAt = null,
    ) {
    }

    /** @return list<string> */
    public function scopeValues(): array
    {
        return array_map(static fn (ApiScope $s) => $s->value, $this->scopes);
    }
}
