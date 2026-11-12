<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Auth\TrustedDevice;
use App\Domain\Auth\TrustedDeviceRepositoryInterface;
use DateTimeImmutable;
use PDO;

final class MariadbTrustedDeviceRepository implements TrustedDeviceRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function save(TrustedDevice $device): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO trusted_devices (id, user_id, selector, token_hash, user_agent, ip_address, created_at, expires_at)
             VALUES (:id, :user_id, :selector, :token_hash, :user_agent, :ip_address, :created_at, :expires_at)'
        );

        $stmt->execute([
            'id' => $device->id,
            'user_id' => $device->userId,
            'selector' => $device->selector,
            'token_hash' => $device->tokenHash,
            'user_agent' => $device->userAgent,
            'ip_address' => $device->ipAddress,
            'created_at' => $device->createdAt->format('Y-m-d H:i:s'),
            'expires_at' => $device->expiresAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function findBySelector(string $selector): ?TrustedDevice
    {
        $stmt = $this->pdo->prepare('SELECT * FROM trusted_devices WHERE selector = :selector');
        $stmt->execute(['selector' => $selector]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findActiveByUser(string $userId, DateTimeImmutable $now): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM trusted_devices WHERE user_id = :user_id AND expires_at > :now ORDER BY created_at DESC');
        $stmt->execute(['user_id' => $userId, 'now' => $now->format('Y-m-d H:i:s')]);

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function deleteById(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM trusted_devices WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public function deleteAllForUser(string $userId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM trusted_devices WHERE user_id = :user_id');
        $stmt->execute(['user_id' => $userId]);
    }

    public function deleteExpired(DateTimeImmutable $now): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM trusted_devices WHERE expires_at <= :now');
        $stmt->execute(['now' => $now->format('Y-m-d H:i:s')]);
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): TrustedDevice
    {
        return new TrustedDevice(
            id: $row['id'],
            userId: $row['user_id'],
            selector: $row['selector'],
            tokenHash: $row['token_hash'],
            userAgent: $row['user_agent'],
            ipAddress: $row['ip_address'],
            createdAt: new DateTimeImmutable($row['created_at']),
            expiresAt: new DateTimeImmutable($row['expires_at']),
        );
    }
}
