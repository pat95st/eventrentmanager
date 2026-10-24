<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Push\PushSubscription;
use App\Domain\Push\PushSubscriptionRepositoryInterface;
use DateTimeImmutable;
use PDO;

final class MariadbPushSubscriptionRepository implements PushSubscriptionRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function save(PushSubscription $subscription): void
    {
        // INSERT ... ON DUPLICATE KEY UPDATE über den UNIQUE-Constraint auf
        // "endpoint" (siehe Migration 043): ein erneutes Abonnieren
        // desselben Geräts/Browsers aktualisiert den bestehenden Datensatz
        // statt einen Duplikat-Fehler zu werfen.
        $stmt = $this->pdo->prepare(
            'INSERT INTO push_subscriptions (id, user_id, endpoint, p256dh, auth, user_agent, created_at)
             VALUES (:id, :user_id, :endpoint, :p256dh, :auth, :user_agent, :created_at)
             ON DUPLICATE KEY UPDATE user_id = VALUES(user_id), p256dh = VALUES(p256dh), auth = VALUES(auth), user_agent = VALUES(user_agent)'
        );

        $stmt->execute([
            'id' => $subscription->id,
            'user_id' => $subscription->userId,
            'endpoint' => $subscription->endpoint,
            'p256dh' => $subscription->p256dh,
            'auth' => $subscription->auth,
            'user_agent' => $subscription->userAgent,
            'created_at' => $subscription->createdAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function findById(string $id): ?PushSubscription
    {
        $stmt = $this->pdo->prepare('SELECT * FROM push_subscriptions WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row === false ? null : $this->hydrate($row);
    }

    public function findByEndpoint(string $endpoint): ?PushSubscription
    {
        $stmt = $this->pdo->prepare('SELECT * FROM push_subscriptions WHERE endpoint = :endpoint');
        $stmt->execute(['endpoint' => $endpoint]);
        $row = $stmt->fetch();

        return $row === false ? null : $this->hydrate($row);
    }

    public function findAllForUser(string $userId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM push_subscriptions WHERE user_id = :user_id ORDER BY created_at DESC');
        $stmt->execute(['user_id' => $userId]);

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM push_subscriptions WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): PushSubscription
    {
        return new PushSubscription(
            id: $row['id'],
            userId: $row['user_id'],
            endpoint: $row['endpoint'],
            p256dh: $row['p256dh'],
            auth: $row['auth'],
            userAgent: $row['user_agent'],
            createdAt: new DateTimeImmutable($row['created_at']),
        );
    }
}
