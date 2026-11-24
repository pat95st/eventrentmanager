<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Domain\OAuth\OAuthAuthorizationCode;
use App\Domain\OAuth\OAuthAuthorizationCodeRepositoryInterface;
use DateTimeImmutable;

/** In-memory test double for OAuthAuthorizationCodeRepositoryInterface. */
final class InMemoryOAuthAuthorizationCodeRepository implements OAuthAuthorizationCodeRepositoryInterface
{
    /** @var array<string, OAuthAuthorizationCode> */
    public array $codes = [];

    public function save(OAuthAuthorizationCode $code): void
    {
        $this->codes[$code->codeHash] = $code;
    }

    public function consume(string $codeHash): ?OAuthAuthorizationCode
    {
        $code = $this->codes[$codeHash] ?? null;
        unset($this->codes[$codeHash]);

        return $code;
    }

    public function purgeExpired(DateTimeImmutable $now): void
    {
        $this->codes = array_filter($this->codes, static fn (OAuthAuthorizationCode $c) => $c->expiresAt >= $now);
    }
}
