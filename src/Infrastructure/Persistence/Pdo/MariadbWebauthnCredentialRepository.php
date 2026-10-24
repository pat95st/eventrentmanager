<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Auth\WebauthnCredential;
use App\Domain\Auth\WebauthnCredentialRepositoryInterface;
use DateTimeImmutable;
use PDO;

final class MariadbWebauthnCredentialRepository implements WebauthnCredentialRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function save(WebauthnCredential $credential): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO webauthn_credentials (
                id, user_id, credential_id, public_key_cose, sign_count, nickname, created_at, last_used_at
             ) VALUES (
                :id, :user_id, :credential_id, :public_key_cose, :sign_count, :nickname, :created_at, :last_used_at
             )
             ON DUPLICATE KEY UPDATE
                sign_count = :sign_count2, nickname = :nickname2, last_used_at = :last_used_at2'
        );

        $publicKeyCoseJson = json_encode($credential->publicKeyCose, JSON_THROW_ON_ERROR);

        $stmt->execute([
            'id' => $credential->id,
            'user_id' => $credential->userId,
            'credential_id' => $credential->credentialId,
            'public_key_cose' => $publicKeyCoseJson,
            'sign_count' => $credential->signCount,
            'nickname' => $credential->nickname,
            'created_at' => $credential->createdAt->format('Y-m-d H:i:s'),
            'last_used_at' => $credential->lastUsedAt?->format('Y-m-d H:i:s'),
            'sign_count2' => $credential->signCount,
            'nickname2' => $credential->nickname,
            'last_used_at2' => $credential->lastUsedAt?->format('Y-m-d H:i:s'),
        ]);
    }

    public function findById(string $id): ?WebauthnCredential
    {
        $stmt = $this->pdo->prepare('SELECT * FROM webauthn_credentials WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findByCredentialId(string $credentialId): ?WebauthnCredential
    {
        $stmt = $this->pdo->prepare('SELECT * FROM webauthn_credentials WHERE credential_id = :credential_id');
        $stmt->execute(['credential_id' => $credentialId]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findAllForUser(string $userId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM webauthn_credentials WHERE user_id = :user_id ORDER BY created_at ASC');
        $stmt->execute(['user_id' => $userId]);

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function existsCredentialId(string $credentialId): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM webauthn_credentials WHERE credential_id = :credential_id');
        $stmt->execute(['credential_id' => $credentialId]);

        return ((int) $stmt->fetchColumn()) > 0;
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM webauthn_credentials WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    private function hydrate(array $row): WebauthnCredential
    {
        return new WebauthnCredential(
            id: $row['id'],
            userId: $row['user_id'],
            credentialId: $row['credential_id'],
            publicKeyCose: json_decode((string) $row['public_key_cose'], true, 512, JSON_THROW_ON_ERROR),
            signCount: (int) $row['sign_count'],
            nickname: $row['nickname'],
            createdAt: new DateTimeImmutable($row['created_at']),
            lastUsedAt: $row['last_used_at'] !== null ? new DateTimeImmutable($row['last_used_at']) : null,
        );
    }
}
