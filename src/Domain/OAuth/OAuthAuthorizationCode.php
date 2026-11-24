<?php

declare(strict_types=1);

namespace App\Domain\OAuth;

use App\Domain\Api\ApiScope;
use DateTimeImmutable;

/** Single-use authorization code (stored as hash), valid for a few minutes. */
final class OAuthAuthorizationCode
{
    /** @param list<ApiScope> $scopes */
    public function __construct(
        public readonly string $codeHash,
        public readonly string $clientId,
        public readonly string $userId,
        public readonly string $redirectUri,
        public readonly string $codeChallenge,
        public readonly array $scopes,
        public readonly ?string $resource,
        public readonly DateTimeImmutable $expiresAt,
        public readonly DateTimeImmutable $createdAt,
    ) {
    }
}
