<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Auth\WebauthnChallenge;
use App\Domain\Auth\WebauthnChallengeRepositoryInterface;
use App\Domain\Auth\WebauthnChallengeType;
use DateTimeImmutable;
use PDO;

final class MariadbWebauthnChallengeRepository implements WebauthnChallengeRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function save(WebauthnChallenge $challenge): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO webauthn_challenges (id, user_id, challenge, type, expires_at, consumed_at, created_at)
             VALUES (:id, :user_id, :challenge, :type, :expires_at, :consumed_at, :created_at)'
        );

        $stmt->execute([
            'id' => $challenge->id,
            'user_id' => $challenge->userId,
            'challenge' => $challenge->challenge,
            'type' => $challenge->type->value,
            'expires_at' => $challenge->expiresAt->format('Y-m-d H:i:s'),
            'consumed_at' => $challenge->consumedAt?->format('Y-m-d H:i:s'),
            'created_at' => $challenge->createdAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function findById(string $id): ?WebauthnChallenge
    {
        $stmt = $this->pdo->prepare('SELECT * FROM webauthn_challenges WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    /** Einmal verwendet — egal ob erfolgreich oder nicht — nie wieder verwendbar (Replay-Schutz). */
    public function markConsumed(string $id): void
    {
        $stmt = $this->pdo->prepare('UPDATE webauthn_challenges SET consumed_at = :consumed_at WHERE id = :id');
        $stmt->execute([
            'consumed_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
            'id' => $id,
        ]);
    }

    private function hydrate(array $row): WebauthnChallenge
    {
        return new WebauthnChallenge(
            id: $row['id'],
            userId: $row['user_id'],
            challenge: $row['challenge'],
            type: WebauthnChallengeType::from($row['type']),
            expiresAt: new DateTimeImmutable($row['expires_at']),
            consumedAt: $row['consumed_at'] !== null ? new DateTimeImmutable($row['consumed_at']) : null,
            createdAt: new DateTimeImmutable($row['created_at']),
        );
    }
}
