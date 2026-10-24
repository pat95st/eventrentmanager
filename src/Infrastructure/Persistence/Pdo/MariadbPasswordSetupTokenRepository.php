<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Auth\PasswordSetupToken;
use App\Domain\Auth\PasswordSetupTokenRepositoryInterface;
use DateTimeImmutable;
use PDO;

final class MariadbPasswordSetupTokenRepository implements PasswordSetupTokenRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function save(PasswordSetupToken $token): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO password_setup_tokens (id, user_id, token_hash, expires_at, used_at, created_at)
             VALUES (:id, :user_id, :token_hash, :expires_at, :used_at, :created_at)'
        );

        $stmt->execute([
            'id' => $token->id,
            'user_id' => $token->userId,
            'token_hash' => $token->tokenHash,
            'expires_at' => $token->expiresAt->format('Y-m-d H:i:s'),
            'used_at' => $token->usedAt?->format('Y-m-d H:i:s'),
            'created_at' => $token->createdAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function findByTokenHash(string $tokenHash): ?PasswordSetupToken
    {
        $stmt = $this->pdo->prepare('SELECT * FROM password_setup_tokens WHERE token_hash = :token_hash');
        $stmt->execute(['token_hash' => $tokenHash]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function markUsed(string $id): void
    {
        $stmt = $this->pdo->prepare('UPDATE password_setup_tokens SET used_at = :used_at WHERE id = :id');
        $stmt->execute([
            'used_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
            'id' => $id,
        ]);
    }

    public function invalidateAllForUser(string $userId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE password_setup_tokens SET used_at = :used_at WHERE user_id = :user_id AND used_at IS NULL'
        );
        $stmt->execute([
            'used_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
            'user_id' => $userId,
        ]);
    }

    private function hydrate(array $row): PasswordSetupToken
    {
        return new PasswordSetupToken(
            id: $row['id'],
            userId: $row['user_id'],
            tokenHash: $row['token_hash'],
            expiresAt: new DateTimeImmutable($row['expires_at']),
            usedAt: $row['used_at'] !== null ? new DateTimeImmutable($row['used_at']) : null,
            createdAt: new DateTimeImmutable($row['created_at']),
        );
    }
}
