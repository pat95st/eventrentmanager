<?php

declare(strict_types=1);

namespace App\Domain\OAuth;

interface OAuthGrantRepositoryInterface
{
    public function findById(string $id): ?OAuthGrant;

    public function findByRefreshTokenHash(string $hash): ?OAuthGrant;

    public function findByPreviousRefreshTokenHash(string $hash): ?OAuthGrant;

    /** @return list<OAuthGrant> newest first */
    public function findByUser(string $userId): array;

    /** @return list<OAuthGrant> newest first */
    public function findAll(): array;

    /** @return list<OAuthGrant> */
    public function findByClientAndUser(string $clientId, string $userId): array;

    public function save(OAuthGrant $grant): void;

    /** Deletes the grant; its access token is removed with it (foreign key cascade). */
    public function delete(string $id): void;
}
