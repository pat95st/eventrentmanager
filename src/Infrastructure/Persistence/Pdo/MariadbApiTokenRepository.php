<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Api\ApiToken;
use App\Domain\Api\ApiTokenRepositoryInterface;
use DateTimeImmutable;
use PDO;

final class MariadbApiTokenRepository implements ApiTokenRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findAll(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM api_tokens ORDER BY name ASC');

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function findById(string $id): ?ApiToken
    {
        $stmt = $this->pdo->prepare('SELECT * FROM api_tokens WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row !== false ? $this->hydrate($row) : null;
    }

    public function findByTokenHash(string $tokenHash): ?ApiToken
    {
        $stmt = $this->pdo->prepare('SELECT * FROM api_tokens WHERE token_hash = :token_hash');
        $stmt->execute(['token_hash' => $tokenHash]);
        $row = $stmt->fetch();

        return $row !== false ? $this->hydrate($row) : null;
    }

    public function save(ApiToken $token): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO api_tokens (id, name, token_hash, token_last_four, ip_allowlist, enabled, last_used_at, created_at)
             VALUES (:id, :name, :token_hash, :token_last_four, :ip_allowlist, :enabled, :last_used_at, :created_at)
             ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                ip_allowlist = VALUES(ip_allowlist),
                enabled = VALUES(enabled),
                last_used_at = VALUES(last_used_at)'
        );

        $stmt->execute([
            'id' => $token->id,
            'name' => $token->name,
            'token_hash' => $token->tokenHash,
            'token_last_four' => $token->tokenLastFour,
            'ip_allowlist' => $token->ipAllowlist,
            'enabled' => $token->enabled ? 1 : 0,
            'last_used_at' => $token->lastUsedAt?->format('Y-m-d H:i:s'),
            'created_at' => $token->createdAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM api_tokens WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public function touchLastUsed(string $id, DateTimeImmutable $at): void
    {
        $stmt = $this->pdo->prepare('UPDATE api_tokens SET last_used_at = :last_used_at WHERE id = :id');
        $stmt->execute(['id' => $id, 'last_used_at' => $at->format('Y-m-d H:i:s')]);
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): ApiToken
    {
        return new ApiToken(
            id: $row['id'],
            name: $row['name'],
            tokenHash: $row['token_hash'],
            tokenLastFour: $row['token_last_four'],
            ipAllowlist: $row['ip_allowlist'],
            enabled: (bool) $row['enabled'],
            lastUsedAt: $row['last_used_at'] !== null ? new DateTimeImmutable($row['last_used_at']) : null,
            createdAt: new DateTimeImmutable($row['created_at']),
        );
    }
}
