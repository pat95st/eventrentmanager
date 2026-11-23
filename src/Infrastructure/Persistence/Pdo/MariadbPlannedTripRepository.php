<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Logbook\LogbookLocation;
use App\Domain\Logbook\LogbookLocationType;
use App\Domain\PlannedTrip\PlannedTrip;
use App\Domain\PlannedTrip\PlannedTripRepositoryInterface;
use DateTimeImmutable;
use PDO;

final class MariadbPlannedTripRepository implements PlannedTripRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findByRental(string $rentalId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM rental_planned_trips WHERE rental_id = :id ORDER BY trip_date IS NULL, trip_date, sort_order, created_at');
        $stmt->execute(['id' => $rentalId]);

        return array_map($this->hydrate(...), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function findById(string $id): ?PlannedTrip
    {
        $stmt = $this->pdo->prepare('SELECT * FROM rental_planned_trips WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->hydrate($row) : null;
    }

    public function save(PlannedTrip $trip): void
    {
        $location = static fn (?LogbookLocation $l, string $p): array => [
            $p . '_type' => $l?->type->value,
            $p . '_ref_id' => $l?->refId,
            $p . '_label' => $l?->label,
            $p . '_address' => $l?->address,
            $p . '_latitude' => $l?->latitude,
            $p . '_longitude' => $l?->longitude,
        ];
        $values = [
            'id' => $trip->id,
            'rental_id' => $trip->rentalId,
            'sort_order' => $trip->sortOrder,
            'trip_date' => $trip->tripDate?->format('Y-m-d'),
        ] + $location($trip->from, 'from') + $location($trip->to, 'to') + [
            'distance_km' => $trip->distanceKm,
            'km_override' => $trip->kmOverride,
            'default_kind' => $trip->defaultKind,
            'default_signature' => $trip->defaultSignature,
            'created_at' => $trip->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $trip->updatedAt->format('Y-m-d H:i:s'),
        ];
        $columns = array_keys($values);
        $updates = array_map(static fn (string $c) => $c . ' = VALUES(' . $c . ')', array_diff($columns, ['id', 'rental_id', 'created_at']));
        $this->pdo->prepare(
            'INSERT INTO rental_planned_trips (' . implode(', ', $columns) . ') VALUES (:' . implode(', :', $columns) . ')'
            . ' ON DUPLICATE KEY UPDATE ' . implode(', ', $updates)
        )->execute($values);
    }

    public function delete(string $id): void
    {
        $this->pdo->prepare('DELETE FROM rental_planned_trips WHERE id = :id')->execute(['id' => $id]);
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): PlannedTrip
    {
        $location = static function (array $row, string $p): ?LogbookLocation {
            $type = LogbookLocationType::tryFrom((string) ($row[$p . '_type'] ?? ''));

            return $type === null ? null : new LogbookLocation(
                $type,
                $row[$p . '_ref_id'],
                (string) $row[$p . '_label'],
                $row[$p . '_address'],
                $row[$p . '_latitude'] !== null ? (float) $row[$p . '_latitude'] : null,
                $row[$p . '_longitude'] !== null ? (float) $row[$p . '_longitude'] : null,
            );
        };

        return new PlannedTrip(
            id: $row['id'],
            rentalId: $row['rental_id'],
            sortOrder: (int) $row['sort_order'],
            tripDate: $row['trip_date'] !== null ? new DateTimeImmutable($row['trip_date']) : null,
            from: $location($row, 'from'),
            to: $location($row, 'to'),
            distanceKm: $row['distance_km'] !== null ? (int) $row['distance_km'] : null,
            kmOverride: $row['km_override'] !== null ? (int) $row['km_override'] : null,
            defaultKind: $row['default_kind'],
            defaultSignature: $row['default_signature'],
            createdAt: new DateTimeImmutable($row['created_at']),
            updatedAt: new DateTimeImmutable($row['updated_at']),
        );
    }
}
