<?php

declare(strict_types=1);

namespace App\Application\OAuth;

use App\Domain\OAuth\OAuthGrant;
use DateTimeImmutable;

/** A connected AI assistant as listed in the profile and under Settings › API. */
final class OAuthConnection
{
    public function __construct(
        public readonly OAuthGrant $grant,
        public readonly string $clientName,
        public readonly string $redirectHost,
        public readonly string $userName,
        public readonly ?DateTimeImmutable $lastUsedAt,
    ) {
    }
}
