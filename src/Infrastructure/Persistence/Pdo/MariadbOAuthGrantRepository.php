<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Api\ApiScope;
use App\Domain\OAuth\OAuthGrant;
use App\Domain\OAuth\OAuthGrantRepositoryInterface;
use DateTimeImmutable;
use PDO;

final class MariadbOAuthGrantRepository implements OAuthGrantRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findById(string $id): ?OAuthGrant
    {
        return $this->findOne('id', $id);
    }

    public function findByRefreshTokenHash(string $hash): ?OAuthGrant
    {
        return $this->findOne('refresh_token_hash', $hash);
    }

    public function findByPreviousRefreshTokenHash(string $hash): ?OAuthGrant
    {
        return $this->findOne('previous_refresh_token_hash', $hash);
    }

    public function findByUser(string $userId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM oauth_grants WHERE user_id = :user_id ORDER BY created_at DESC');
        $stmt->execute(['user_id' => $userId]);

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function findAll(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM oauth_grants ORDER BY created_at DESC');

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function findByClientAndUser(string $clientId, string $userId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM oauth_grants WHERE client_id = :client_id AND user_id = :user_id');
        $stmt->execute(['client_id' => $clientId, 'user_id' => $userId]);

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function save(OAuthGrant $grant): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO oauth_grants (id, client_id, user_id, scopes, refresh_token_hash, previous_refresh_token_hash, refresh_expires_at, created_at, last_used_at)
             VALUES (:id, :client_id, :user_id, :scopes, :refresh_token_hash, :previous_refresh_token_hash, :refresh_expires_at, :created_at, :last_used_at)
             ON DUPLICATE KEY UPDATE
                scopes = VALUES(scopes),
                refresh_token_hash = VALUES(refresh_token_hash),
                previous_refresh_token_hash = VALUES(previous_refresh_token_hash),
                refresh_expires_at = VALUES(refresh_expires_at),
                last_used_at = VALUES(last_used_at)'
        );
        $stmt->execute([
            'id' => $grant->id,
            'client_id' => $grant->clientId,
            'user_id' => $grant->userId,
            'scopes' => json_encode($grant->scopeValues(), JSON_THROW_ON_ERROR),
            'refresh_token_hash' => $grant->refreshTokenHash,
            'previous_refresh_token_hash' => $grant->previousRefreshTokenHash,
            'refresh_expires_at' => $grant->refreshExpiresAt->format('Y-m-d H:i:s'),
            'created_at' => $grant->createdAt->format('Y-m-d H:i:s'),
            'last_used_at' => $grant->lastUsedAt?->format('Y-m-d H:i:s'),
        ]);
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM oauth_grants WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    private function findOne(string $column, string $value): ?OAuthGrant
    {
        $stmt = $this->pdo->prepare('SELECT * FROM oauth_grants WHERE ' . $column . ' = :value LIMIT 1');
        $stmt->execute(['value' => $value]);
        $row = $stmt->fetch();

        return $row !== false ? $this->hydrate($row) : null;
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): OAuthGrant
    {
        return new OAuthGrant(
            id: $row['id'],
            clientId: $row['client_id'],
            userId: $row['user_id'],
            scopes: ApiScope::parseList((array) (json_decode((string) $row['scopes'], true) ?? [])),
            refreshTokenHash: $row['refresh_token_hash'],
            previousRefreshTokenHash: $row['previous_refresh_token_hash'] ?? null,
            refreshExpiresAt: new DateTimeImmutable($row['refresh_expires_at']),
            createdAt: new DateTimeImmutable($row['created_at']),
            lastUsedAt: isset($row['last_used_at']) ? new DateTimeImmutable($row['last_used_at']) : null,
        );
    }
}
