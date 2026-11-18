<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Routing\RouteCacheRepositoryInterface;
use PDO;

final class MariadbRouteCacheRepository implements RouteCacheRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function find(string $rentalId, string $hash): ?array
    {
        $stmt = $this->pdo->prepare('SELECT payload FROM rental_route_cache WHERE rental_id = :id AND hash = :hash');
        $stmt->execute(['id' => $rentalId, 'hash' => $hash]);
        $payload = $stmt->fetchColumn();
        $data = is_string($payload) ? json_decode($payload, true) : null;

        return is_array($data) ? $data : null;
    }

    public function store(string $rentalId, string $hash, array $payload): void
    {
        $this->pdo->prepare(
            'INSERT INTO rental_route_cache (rental_id, hash, payload, created_at) VALUES (:id, :hash, :payload, NOW())
             ON DUPLICATE KEY UPDATE hash = VALUES(hash), payload = VALUES(payload), created_at = VALUES(created_at)'
        )->execute(['id' => $rentalId, 'hash' => $hash, 'payload' => json_encode($payload, JSON_THROW_ON_ERROR)]);
    }
}
