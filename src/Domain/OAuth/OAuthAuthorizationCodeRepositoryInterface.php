<?php

declare(strict_types=1);

namespace App\Domain\OAuth;

use DateTimeImmutable;

interface OAuthAuthorizationCodeRepositoryInterface
{
    public function save(OAuthAuthorizationCode $code): void;

    /** Returns the code and deletes it in one go (codes are single use). */
    public function consume(string $codeHash): ?OAuthAuthorizationCode;

    public function purgeExpired(DateTimeImmutable $now): void;
}
