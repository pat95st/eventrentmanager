<?php

declare(strict_types=1);

namespace App\Infrastructure\DocumentStorage\Cloud;

use DateTimeImmutable;

final class OAuthTokenSet
{
    public function __construct(
        public readonly string $accessToken,
        public readonly string $refreshToken,
        public readonly DateTimeImmutable $expiresAt,
    ) {
    }
}
