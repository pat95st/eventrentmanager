<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Auth\Impersonation;
use App\Domain\Auth\ImpersonationRepositoryInterface;
use DateTimeImmutable;
use PDO;

final class MariadbImpersonationRepository implements ImpersonationRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function save(Impersonation $impersonation): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO impersonations (id, admin_user_id, target_user_id, started_at, ended_at)
             VALUES (:id, :admin_user_id, :target_user_id, :started_at, :ended_at)
             ON DUPLICATE KEY UPDATE ended_at = :ended_at2'
        );

        $stmt->execute([
            'id' => $impersonation->id,
            'admin_user_id' => $impersonation->adminUserId,
            'target_user_id' => $impersonation->targetUserId,
            'started_at' => $impersonation->startedAt->format('Y-m-d H:i:s'),
            'ended_at' => $impersonation->endedAt?->format('Y-m-d H:i:s'),
            'ended_at2' => $impersonation->endedAt?->format('Y-m-d H:i:s'),
        ]);
    }

    public function findById(string $id): ?Impersonation
    {
        $stmt = $this->pdo->prepare('SELECT * FROM impersonations WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findAll(?int $limit = null, ?int $offset = null): array
    {
        $sql = 'SELECT * FROM impersonations ORDER BY started_at DESC';

        if ($limit !== null) {
            $sql .= ' LIMIT ' . max(1, $limit) . ' OFFSET ' . max(0, $offset ?? 0);
        }

        $stmt = $this->pdo->query($sql);

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function countAll(): int
    {
        return (int) $this->pdo->query('SELECT COUNT(*) FROM impersonations')->fetchColumn();
    }

    private function hydrate(array $row): Impersonation
    {
        return new Impersonation(
            id: $row['id'],
            adminUserId: $row['admin_user_id'],
            targetUserId: $row['target_user_id'],
            startedAt: new DateTimeImmutable($row['started_at']),
            endedAt: $row['ended_at'] !== null ? new DateTimeImmutable($row['ended_at']) : null,
        );
    }
}
