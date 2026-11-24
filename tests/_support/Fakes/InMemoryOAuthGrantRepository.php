<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Domain\OAuth\OAuthGrant;
use App\Domain\OAuth\OAuthGrantRepositoryInterface;

/**
 * In-memory test double for OAuthGrantRepositoryInterface. Deleting a grant
 * also deletes its access tokens in the linked token repository, like the
 * foreign key cascade in the database.
 */
final class InMemoryOAuthGrantRepository implements OAuthGrantRepositoryInterface
{
    /** @var array<string, OAuthGrant> */
    public array $grants = [];

    public function __construct(private readonly ?InMemoryApiTokenRepository $tokens = null)
    {
    }

    public function findById(string $id): ?OAuthGrant
    {
        return $this->grants[$id] ?? null;
    }

    public function findByRefreshTokenHash(string $hash): ?OAuthGrant
    {
        foreach ($this->grants as $grant) {
            if ($grant->refreshTokenHash === $hash) {
                return $grant;
            }
        }

        return null;
    }

    public function findByPreviousRefreshTokenHash(string $hash): ?OAuthGrant
    {
        foreach ($this->grants as $grant) {
            if ($grant->previousRefreshTokenHash === $hash) {
                return $grant;
            }
        }

        return null;
    }

    public function findByUser(string $userId): array
    {
        return array_values(array_filter($this->grants, static fn (OAuthGrant $g) => $g->userId === $userId));
    }

    public function findAll(): array
    {
        return array_values($this->grants);
    }

    public function findByClientAndUser(string $clientId, string $userId): array
    {
        return array_values(array_filter($this->grants, static fn (OAuthGrant $g) => $g->clientId === $clientId && $g->userId === $userId));
    }

    public function save(OAuthGrant $grant): void
    {
        $this->grants[$grant->id] = clone $grant;
    }

    public function delete(string $id): void
    {
        unset($this->grants[$id]);
        $this->tokens?->deleteByOAuthGrant($id);
    }
}
