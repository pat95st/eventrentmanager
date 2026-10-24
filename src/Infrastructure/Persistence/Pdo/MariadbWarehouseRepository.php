<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Warehouse\Warehouse;
use App\Domain\Warehouse\WarehouseRepositoryInterface;
use DateTimeImmutable;
use PDO;

final class MariadbWarehouseRepository implements WarehouseRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function save(Warehouse $warehouse): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO warehouses (
                id, name, street, postal_code, city, country, notes,
                latitude, longitude, created_at, updated_at
             ) VALUES (
                :id, :name, :street, :postal_code, :city, :country, :notes,
                :latitude, :longitude, :created_at, :updated_at
             )
             ON DUPLICATE KEY UPDATE
                name = :name2, street = :street2, postal_code = :postal_code2, city = :city2,
                country = :country2, notes = :notes2, latitude = :latitude2, longitude = :longitude2,
                updated_at = :updated_at2'
        );

        $stmt->execute([
            'id' => $warehouse->id,
            'name' => $warehouse->name,
            'street' => $warehouse->street,
            'postal_code' => $warehouse->postalCode,
            'city' => $warehouse->city,
            'country' => $warehouse->country,
            'notes' => $warehouse->notes,
            'latitude' => $warehouse->latitude,
            'longitude' => $warehouse->longitude,
            'created_at' => $warehouse->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $warehouse->updatedAt->format('Y-m-d H:i:s'),
            'name2' => $warehouse->name,
            'street2' => $warehouse->street,
            'postal_code2' => $warehouse->postalCode,
            'city2' => $warehouse->city,
            'country2' => $warehouse->country,
            'notes2' => $warehouse->notes,
            'latitude2' => $warehouse->latitude,
            'longitude2' => $warehouse->longitude,
            'updated_at2' => $warehouse->updatedAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function findById(string $id): ?Warehouse
    {
        $stmt = $this->pdo->prepare('SELECT * FROM warehouses WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findAll(?string $search = null, ?int $limit = null, ?int $offset = null): array
    {
        [$where, $params] = $this->buildSearchWhere($search);

        $sql = 'SELECT * FROM warehouses' . $where . ' ORDER BY name ASC';
        if ($limit !== null) {
            $sql .= ' LIMIT :limit OFFSET :offset';
        }

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        if ($limit !== null) {
            $stmt->bindValue('limit', max(0, $limit), PDO::PARAM_INT);
            $stmt->bindValue('offset', max(0, $offset ?? 0), PDO::PARAM_INT);
        }
        $stmt->execute();

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function countAll(?string $search = null): int
    {
        [$where, $params] = $this->buildSearchWhere($search);

        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM warehouses' . $where);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /** @return array{0: string, 1: array<string, string>} */
    private function buildSearchWhere(?string $search): array
    {
        if ($search === null || trim($search) === '') {
            return ['', []];
        }

        $needle = '%' . $search . '%';

        return [' WHERE name LIKE :search OR city LIKE :search2', ['search' => $needle, 'search2' => $needle]];
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM warehouses WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    private function hydrate(array $row): Warehouse
    {
        return new Warehouse(
            id: $row['id'],
            name: $row['name'],
            street: $row['street'],
            postalCode: $row['postal_code'],
            city: $row['city'],
            country: $row['country'],
            notes: $row['notes'],
            latitude: $row['latitude'] !== null ? (float) $row['latitude'] : null,
            longitude: $row['longitude'] !== null ? (float) $row['longitude'] : null,
            createdAt: new DateTimeImmutable($row['created_at']),
            updatedAt: new DateTimeImmutable($row['updated_at']),
        );
    }
}
