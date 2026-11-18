<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Domain\Api\ApiToken;
use App\Domain\Api\ApiTokenRepositoryInterface;
use DateTimeImmutable;

/** In-memory test double for ApiTokenRepositoryInterface (see tests/README.md). */
final class InMemoryApiTokenRepository implements ApiTokenRepositoryInterface
{
    /** @var array<string, ApiToken> */
    private array $tokens = [];

    public function findAll(): array
    {
        return array_values($this->tokens);
    }

    public function findById(string $id): ?ApiToken
    {
        return $this->tokens[$id] ?? null;
    }

    public function findByTokenHash(string $tokenHash): ?ApiToken
    {
        foreach ($this->tokens as $token) {
            if ($token->tokenHash === $tokenHash) {
                return $token;
            }
        }

        return null;
    }

    public function save(ApiToken $token): void
    {
        $this->tokens[$token->id] = $token;
    }

    public function delete(string $id): void
    {
        unset($this->tokens[$id]);
    }

    public function touchLastUsed(string $id, DateTimeImmutable $at): void
    {
        if (isset($this->tokens[$id])) {
            $this->tokens[$id]->lastUsedAt = $at;
        }
    }
}
