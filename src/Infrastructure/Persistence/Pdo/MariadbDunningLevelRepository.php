<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Dunning\DunningFeeType;
use App\Domain\Dunning\DunningLevel;
use App\Domain\Dunning\DunningLevelRepositoryInterface;
use DateTimeImmutable;
use PDO;

final class MariadbDunningLevelRepository implements DunningLevelRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findAllOrdered(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM dunning_levels ORDER BY days_after_due ASC');

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function findById(string $id): ?DunningLevel
    {
        $stmt = $this->pdo->prepare('SELECT * FROM dunning_levels WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function existsByDaysAfterDue(int $daysAfterDue, ?string $excludeId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM dunning_levels WHERE days_after_due = :days';
        if ($excludeId !== null) {
            $sql .= ' AND id != :exclude_id';
        }

        $stmt = $this->pdo->prepare($sql);
        $params = ['days' => $daysAfterDue];
        if ($excludeId !== null) {
            $params['exclude_id'] = $excludeId;
        }
        $stmt->execute($params);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function save(DunningLevel $level): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO dunning_levels (id, name, days_after_due, fee_type, fee_value, created_at, updated_at)
             VALUES (:id, :name, :days_after_due, :fee_type, :fee_value, :created_at, :updated_at)
             ON DUPLICATE KEY UPDATE name = :name2, days_after_due = :days_after_due2, fee_type = :fee_type2, fee_value = :fee_value2, updated_at = :updated_at2'
        );

        $stmt->execute([
            'id' => $level->id,
            'name' => $level->name,
            'days_after_due' => $level->daysAfterDue,
            'fee_type' => $level->feeType->value,
            'fee_value' => $level->feeValue,
            'created_at' => $level->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $level->updatedAt->format('Y-m-d H:i:s'),
            'name2' => $level->name,
            'days_after_due2' => $level->daysAfterDue,
            'fee_type2' => $level->feeType->value,
            'fee_value2' => $level->feeValue,
            'updated_at2' => $level->updatedAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM dunning_levels WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    private function hydrate(array $row): DunningLevel
    {
        return new DunningLevel(
            id: $row['id'],
            name: $row['name'],
            daysAfterDue: (int) $row['days_after_due'],
            feeType: DunningFeeType::from($row['fee_type']),
            feeValue: $row['fee_value'],
            createdAt: new DateTimeImmutable($row['created_at']),
            updatedAt: new DateTimeImmutable($row['updated_at']),
        );
    }
}
