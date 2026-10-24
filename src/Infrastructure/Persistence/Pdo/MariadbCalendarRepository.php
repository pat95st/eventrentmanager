<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Calendar\Calendar;
use App\Domain\Calendar\CalendarKind;
use App\Domain\Calendar\CalendarRepositoryInterface;
use DateTimeImmutable;
use PDO;

final class MariadbCalendarRepository implements CalendarRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function save(Calendar $calendar): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO calendars (
                id, kind, name, enabled, category_id, manufacturer_id, warehouse_id, venue_id, created_at, updated_at
             ) VALUES (
                :id, :kind, :name, :enabled, :category_id, :manufacturer_id, :warehouse_id, :venue_id, :created_at, :updated_at
             )
             ON DUPLICATE KEY UPDATE
                name = :name2, enabled = :enabled2, category_id = :category_id2,
                manufacturer_id = :manufacturer_id2, warehouse_id = :warehouse_id2,
                venue_id = :venue_id2, updated_at = :updated_at2'
        );

        $stmt->execute([
            'id' => $calendar->id,
            'kind' => $calendar->kind->value,
            'name' => $calendar->name,
            'enabled' => $calendar->enabled ? 1 : 0,
            'category_id' => $calendar->categoryId,
            'manufacturer_id' => $calendar->manufacturerId,
            'warehouse_id' => $calendar->warehouseId,
            'venue_id' => $calendar->venueId,
            'created_at' => $calendar->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $calendar->updatedAt->format('Y-m-d H:i:s'),
            'name2' => $calendar->name,
            'enabled2' => $calendar->enabled ? 1 : 0,
            'category_id2' => $calendar->categoryId,
            'manufacturer_id2' => $calendar->manufacturerId,
            'warehouse_id2' => $calendar->warehouseId,
            'venue_id2' => $calendar->venueId,
            'updated_at2' => $calendar->updatedAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function findById(string $id): ?Calendar
    {
        $stmt = $this->pdo->prepare('SELECT * FROM calendars WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findAll(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM calendars ORDER BY (kind = 'event') DESC, name ASC");

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare("DELETE FROM calendars WHERE id = :id AND kind != 'event'");
        $stmt->execute(['id' => $id]);
    }

    private function hydrate(array $row): Calendar
    {
        return new Calendar(
            id: $row['id'],
            kind: CalendarKind::from($row['kind']),
            name: $row['name'],
            enabled: (bool) $row['enabled'],
            categoryId: $row['category_id'],
            manufacturerId: $row['manufacturer_id'],
            warehouseId: $row['warehouse_id'],
            venueId: $row['venue_id'],
            createdAt: new DateTimeImmutable($row['created_at']),
            updatedAt: new DateTimeImmutable($row['updated_at']),
        );
    }
}
