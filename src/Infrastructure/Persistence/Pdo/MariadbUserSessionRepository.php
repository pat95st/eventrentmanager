<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Auth\UserSession;
use App\Domain\Auth\UserSessionRepositoryInterface;
use DateTimeImmutable;
use PDO;

final class MariadbUserSessionRepository implements UserSessionRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function upsert(UserSession $session): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO user_sessions (id, user_id, user_agent, ip_address, created_at, last_seen_at)
             VALUES (:id, :user_id, :user_agent, :ip_address, :created_at, :last_seen_at)
             ON DUPLICATE KEY UPDATE
                user_agent = :user_agent2, ip_address = :ip_address2, last_seen_at = :last_seen_at2'
        );

        $stmt->execute([
            'id' => $session->id,
            'user_id' => $session->userId,
            'user_agent' => $session->userAgent,
            'ip_address' => $session->ipAddress,
            'created_at' => $session->createdAt->format('Y-m-d H:i:s'),
            'last_seen_at' => $session->lastSeenAt->format('Y-m-d H:i:s'),
            'user_agent2' => $session->userAgent,
            'ip_address2' => $session->ipAddress,
            'last_seen_at2' => $session->lastSeenAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function findByUser(string $userId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM user_sessions WHERE user_id = :user_id ORDER BY last_seen_at DESC');
        $stmt->execute(['user_id' => $userId]);

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function findById(string $id): ?UserSession
    {
        $stmt = $this->pdo->prepare('SELECT * FROM user_sessions WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function deleteById(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM user_sessions WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public function deleteAllForUserExcept(string $userId, string $exceptId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM user_sessions WHERE user_id = :user_id AND id <> :except_id');
        $stmt->execute(['user_id' => $userId, 'except_id' => $exceptId]);
    }

    public function deleteAllForUser(string $userId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM user_sessions WHERE user_id = :user_id');
        $stmt->execute(['user_id' => $userId]);
    }

    private function hydrate(array $row): UserSession
    {
        return new UserSession(
            id: $row['id'],
            userId: $row['user_id'],
            userAgent: $row['user_agent'],
            ipAddress: $row['ip_address'],
            createdAt: new DateTimeImmutable($row['created_at']),
            lastSeenAt: new DateTimeImmutable($row['last_seen_at']),
        );
    }
}
