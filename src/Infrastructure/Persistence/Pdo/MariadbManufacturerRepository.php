<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Manufacturer\Manufacturer;
use App\Domain\Manufacturer\ManufacturerRepositoryInterface;
use DateTimeImmutable;
use PDO;

final class MariadbManufacturerRepository implements ManufacturerRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function save(Manufacturer $manufacturer): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO manufacturers (id, name, prefix, created_at, updated_at)
             VALUES (:id, :name, :prefix, :created_at, :updated_at)
             ON DUPLICATE KEY UPDATE name = :name2, prefix = :prefix2, updated_at = :updated_at2'
        );

        $stmt->execute([
            'id' => $manufacturer->id,
            'name' => $manufacturer->name,
            'prefix' => $manufacturer->prefix,
            'created_at' => $manufacturer->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $manufacturer->updatedAt->format('Y-m-d H:i:s'),
            'name2' => $manufacturer->name,
            'prefix2' => $manufacturer->prefix,
            'updated_at2' => $manufacturer->updatedAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function findById(string $id): ?Manufacturer
    {
        $stmt = $this->pdo->prepare('SELECT * FROM manufacturers WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findAll(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM manufacturers ORDER BY name ASC');

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function existsByName(string $name, ?string $excludeId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM manufacturers WHERE name = :name';
        if ($excludeId !== null) {
            $sql .= ' AND id != :exclude_id';
        }

        $stmt = $this->pdo->prepare($sql);
        $params = ['name' => $name];
        if ($excludeId !== null) {
            $params['exclude_id'] = $excludeId;
        }
        $stmt->execute($params);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function existsByPrefix(string $prefix, ?string $excludeId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM manufacturers WHERE prefix = :prefix';
        if ($excludeId !== null) {
            $sql .= ' AND id != :exclude_id';
        }

        $stmt = $this->pdo->prepare($sql);
        $params = ['prefix' => $prefix];
        if ($excludeId !== null) {
            $params['exclude_id'] = $excludeId;
        }
        $stmt->execute($params);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function isUsedByArticles(string $id): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM articles WHERE manufacturer_id = :id');
        $stmt->execute(['id' => $id]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function isUsedByCaseItems(string $id): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM case_items WHERE manufacturer_id = :id');
        $stmt->execute(['id' => $id]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM manufacturers WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    private function hydrate(array $row): Manufacturer
    {
        return new Manufacturer(
            id: $row['id'],
            name: $row['name'],
            prefix: $row['prefix'],
            createdAt: new DateTimeImmutable($row['created_at']),
            updatedAt: new DateTimeImmutable($row['updated_at']),
        );
    }
}
