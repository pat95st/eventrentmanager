<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Auth\LoginChallenge;
use App\Domain\Auth\LoginChallengeRepositoryInterface;
use DateTimeImmutable;
use PDO;

final class MariadbLoginChallengeRepository implements LoginChallengeRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function save(LoginChallenge $challenge): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO login_challenges (id, user_id, code_hash, expires_at, consumed_at, created_at)
             VALUES (:id, :user_id, :code_hash, :expires_at, :consumed_at, :created_at)
             ON DUPLICATE KEY UPDATE
                code_hash = :code_hash2, expires_at = :expires_at2, consumed_at = :consumed_at2'
        );

        $stmt->execute([
            'id' => $challenge->id,
            'user_id' => $challenge->userId,
            'code_hash' => $challenge->codeHash,
            'expires_at' => $challenge->expiresAt->format('Y-m-d H:i:s'),
            'consumed_at' => $challenge->consumedAt?->format('Y-m-d H:i:s'),
            'created_at' => $challenge->createdAt->format('Y-m-d H:i:s'),
            'code_hash2' => $challenge->codeHash,
            'expires_at2' => $challenge->expiresAt->format('Y-m-d H:i:s'),
            'consumed_at2' => $challenge->consumedAt?->format('Y-m-d H:i:s'),
        ]);
    }

    public function findLatestActiveForUser(string $userId): ?LoginChallenge
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM login_challenges
             WHERE user_id = :user_id AND consumed_at IS NULL
             ORDER BY created_at DESC
             LIMIT 1'
        );
        $stmt->execute(['user_id' => $userId]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findById(string $id): ?LoginChallenge
    {
        $stmt = $this->pdo->prepare('SELECT * FROM login_challenges WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    private function hydrate(array $row): LoginChallenge
    {
        return new LoginChallenge(
            id: $row['id'],
            userId: $row['user_id'],
            codeHash: $row['code_hash'],
            expiresAt: new DateTimeImmutable($row['expires_at']),
            consumedAt: $row['consumed_at'] !== null ? new DateTimeImmutable($row['consumed_at']) : null,
            createdAt: new DateTimeImmutable($row['created_at']),
        );
    }
}
