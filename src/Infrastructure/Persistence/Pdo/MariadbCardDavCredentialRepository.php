<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\AddressBook\CardDavCredential;
use App\Domain\AddressBook\CardDavCredentialRepositoryInterface;
use DateTimeImmutable;
use PDO;

final class MariadbCardDavCredentialRepository implements CardDavCredentialRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function save(CardDavCredential $credential): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO carddav_credentials (id, user_id, name, token_hash, token_last_four, last_used_at, created_at)
             VALUES (:id, :user_id, :name, :token_hash, :token_last_four, :last_used_at, :created_at)
             ON DUPLICATE KEY UPDATE name = :name2, last_used_at = :last_used_at2'
        );

        $stmt->execute([
            'id' => $credential->id,
            'user_id' => $credential->userId,
            'name' => $credential->name,
            'token_hash' => $credential->tokenHash,
            'token_last_four' => $credential->tokenLastFour,
            'last_used_at' => $credential->lastUsedAt?->format('Y-m-d H:i:s'),
            'created_at' => $credential->createdAt->format('Y-m-d H:i:s'),
            'name2' => $credential->name,
            'last_used_at2' => $credential->lastUsedAt?->format('Y-m-d H:i:s'),
        ]);
    }

    public function findById(string $id): ?CardDavCredential
    {
        $stmt = $this->pdo->prepare('SELECT * FROM carddav_credentials WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findByTokenHash(string $tokenHash): ?CardDavCredential
    {
        $stmt = $this->pdo->prepare('SELECT * FROM carddav_credentials WHERE token_hash = :token_hash');
        $stmt->execute(['token_hash' => $tokenHash]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findByUser(string $userId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM carddav_credentials WHERE user_id = :user_id ORDER BY created_at DESC');
        $stmt->execute(['user_id' => $userId]);

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function touchLastUsed(string $id, DateTimeImmutable $at): void
    {
        $stmt = $this->pdo->prepare('UPDATE carddav_credentials SET last_used_at = :at WHERE id = :id');
        $stmt->execute(['at' => $at->format('Y-m-d H:i:s'), 'id' => $id]);
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM carddav_credentials WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    private function hydrate(array $row): CardDavCredential
    {
        return new CardDavCredential(
            id: $row['id'],
            userId: $row['user_id'],
            name: $row['name'],
            tokenHash: $row['token_hash'],
            tokenLastFour: $row['token_last_four'],
            lastUsedAt: $row['last_used_at'] !== null ? new DateTimeImmutable($row['last_used_at']) : null,
            createdAt: new DateTimeImmutable($row['created_at']),
        );
    }
}
