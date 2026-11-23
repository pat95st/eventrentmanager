<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Logbook\LogbookCategory;
use App\Domain\Logbook\LogbookEntry;
use App\Domain\Logbook\LogbookEntryChange;
use App\Domain\Logbook\LogbookEntryRepositoryInterface;
use App\Domain\Logbook\LogbookFilter;
use App\Domain\Logbook\LogbookLocation;
use App\Domain\Logbook\LogbookLocationType;
use App\Domain\Logbook\LogbookStatus;
use DateTimeImmutable;
use PDO;

final class MariadbLogbookEntryRepository implements LogbookEntryRepositoryInterface
{
    private const COLUMNS = [
        'trip_date', 'depart_time', 'arrive_time', 'driver_user_id', 'driver_name', 'driver_phone', 'is_external',
        'vehicle_id', 'other_vehicle_label', 'trailer_id', 'category', 'rental_id', 'tour_stop_id', 'purpose',
        'from_type', 'from_ref_id', 'from_label', 'from_address', 'from_latitude', 'from_longitude',
        'to_type', 'to_ref_id', 'to_label', 'to_address', 'to_latitude', 'to_longitude',
        'round_trip', 'distance_km', 'odometer_start', 'odometer_end', 'not_billed', 'status', 'odometer_check_bypassed',
        'created_by_user_id', 'created_at', 'updated_at', 'deleted_at', 'deleted_by_user_id',
    ];

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function save(LogbookEntry $entry): void
    {
        $values = $this->values($entry);
        $columns = self::COLUMNS;
        $updates = array_map(static fn (string $c) => $c . ' = VALUES(' . $c . ')', array_diff($columns, ['created_at', 'created_by_user_id']));
        $sql = 'INSERT INTO logbook_entries (id, ' . implode(', ', $columns) . ') VALUES (:id, :' . implode(', :', $columns) . ')'
            . ' ON DUPLICATE KEY UPDATE ' . implode(', ', $updates);
        $this->pdo->prepare($sql)->execute(['id' => $entry->id] + $values);
    }

    public function findById(string $id): ?LogbookEntry
    {
        $stmt = $this->pdo->prepare('SELECT * FROM logbook_entries WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->hydrate($row) : null;
    }

    public function search(LogbookFilter $filter, ?int $limit = null, int $offset = 0): array
    {
        [$where, $params] = $this->where($filter);
        $sql = 'SELECT * FROM logbook_entries' . $where
            . ' ORDER BY trip_date DESC, COALESCE(depart_time, \'23:59:59\') DESC, created_at DESC';
        if ($limit !== null) {
            $sql .= ' LIMIT ' . max(0, $limit) . ' OFFSET ' . max(0, $offset);
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return array_map($this->hydrate(...), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function count(LogbookFilter $filter): int
    {
        [$where, $params] = $this->where($filter);
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM logbook_entries' . $where);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    public function totals(LogbookFilter $filter): array
    {
        [$where, $params] = $this->where($filter);
        $stmt = $this->pdo->prepare('SELECT category, COUNT(*) AS n, COALESCE(SUM(distance_km), 0) AS km FROM logbook_entries' . $where . ' GROUP BY category');
        $stmt->execute($params);
        $totals = ['count' => 0, 'km' => 0, 'byCategory' => []];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $totals['count'] += (int) $row['n'];
            $totals['km'] += (int) $row['km'];
            $totals['byCategory'][(string) $row['category']] = (int) $row['km'];
        }

        return $totals;
    }

    public function countByVehicle(string $vehicleId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM logbook_entries WHERE vehicle_id = :id OR trailer_id = :id2');
        $stmt->execute(['id' => $vehicleId, 'id2' => $vehicleId]);

        return (int) $stmt->fetchColumn();
    }

    public function addChange(LogbookEntryChange $change): void
    {
        $this->pdo->prepare(
            'INSERT INTO logbook_entry_changes (id, entry_id, user_id, actor_name, action, changes, created_at)
             VALUES (:id, :entry_id, :user_id, :actor_name, :action, :changes, :created_at)'
        )->execute([
            'id' => $change->id,
            'entry_id' => $change->entryId,
            'user_id' => $change->userId,
            'actor_name' => $change->actorName,
            'action' => $change->action,
            'changes' => json_encode($change->changes, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'created_at' => $change->createdAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function findChanges(string $entryId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM logbook_entry_changes WHERE entry_id = :id ORDER BY created_at ASC, id ASC');
        $stmt->execute(['id' => $entryId]);

        return array_map(static fn (array $row) => new LogbookEntryChange(
            $row['id'],
            $row['entry_id'],
            $row['user_id'],
            $row['actor_name'],
            $row['action'],
            (array) json_decode((string) $row['changes'], true),
            new DateTimeImmutable($row['created_at']),
        ), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return array{0: string, 1: array<string, mixed>} */
    private function where(LogbookFilter $filter): array
    {
        $conditions = [];
        $params = [];
        if (!$filter->includeDeleted) {
            $conditions[] = 'deleted_at IS NULL';
        }
        if ($filter->from !== null) {
            $conditions[] = 'trip_date >= :from';
            $params['from'] = $filter->from->format('Y-m-d');
        }
        if ($filter->to !== null) {
            $conditions[] = 'trip_date <= :to';
            $params['to'] = $filter->to->format('Y-m-d');
        }
        if ($filter->vehicleId !== null) {
            $conditions[] = '(vehicle_id = :vehicle OR trailer_id = :vehicle2)';
            $params['vehicle'] = $filter->vehicleId;
            $params['vehicle2'] = $filter->vehicleId;
        }
        if ($filter->driverUserId !== null) {
            $conditions[] = 'driver_user_id = :driver';
            $params['driver'] = $filter->driverUserId;
        }
        if ($filter->category !== null) {
            $conditions[] = 'category = :category';
            $params['category'] = $filter->category->value;
        }
        if ($filter->rentalId !== null) {
            $conditions[] = 'rental_id = :rental';
            $params['rental'] = $filter->rentalId;
        }
        if ($filter->external !== null) {
            $conditions[] = 'is_external = :external';
            $params['external'] = (int) $filter->external;
        }
        if ($filter->status !== null) {
            $conditions[] = 'status = :status';
            $params['status'] = $filter->status->value;
        }
        if ($filter->ownUserId !== null) {
            $conditions[] = '(is_external = 0 AND (driver_user_id = :own OR created_by_user_id = :own2))';
            $params['own'] = $filter->ownUserId;
            $params['own2'] = $filter->ownUserId;
        }

        return [$conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions), $params];
    }

    /** @return array<string, mixed> */
    private function values(LogbookEntry $entry): array
    {
        $location = static fn (?LogbookLocation $l, string $p): array => [
            $p . '_type' => $l?->type->value,
            $p . '_ref_id' => $l?->refId,
            $p . '_label' => $l?->label,
            $p . '_address' => $l?->address,
            $p . '_latitude' => $l?->latitude,
            $p . '_longitude' => $l?->longitude,
        ];

        return [
            'trip_date' => $entry->tripDate->format('Y-m-d'),
            'depart_time' => $entry->departTime,
            'arrive_time' => $entry->arriveTime,
            'driver_user_id' => $entry->driverUserId,
            'driver_name' => $entry->driverName,
            'driver_phone' => $entry->driverPhone,
            'is_external' => (int) $entry->isExternal,
            'vehicle_id' => $entry->vehicleId,
            'other_vehicle_label' => $entry->otherVehicleLabel,
            'trailer_id' => $entry->trailerId,
            'category' => $entry->category->value,
            'rental_id' => $entry->rentalId,
            'tour_stop_id' => $entry->tourStopId,
            'purpose' => $entry->purpose,
        ] + $location($entry->from, 'from') + $location($entry->to, 'to') + [
            'round_trip' => (int) $entry->roundTrip,
            'distance_km' => $entry->distanceKm,
            'odometer_start' => $entry->odometerStart,
            'odometer_end' => $entry->odometerEnd,
            'not_billed' => (int) $entry->notBilled,
            'status' => $entry->status->value,
            'odometer_check_bypassed' => (int) $entry->odometerCheckBypassed,
            'created_by_user_id' => $entry->createdByUserId,
            'created_at' => $entry->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $entry->updatedAt->format('Y-m-d H:i:s'),
            'deleted_at' => $entry->deletedAt?->format('Y-m-d H:i:s'),
            'deleted_by_user_id' => $entry->deletedByUserId,
        ];
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): LogbookEntry
    {
        $location = static function (array $row, string $p): ?LogbookLocation {
            $type = LogbookLocationType::tryFrom((string) ($row[$p . '_type'] ?? ''));
            if ($type === null) {
                return null;
            }

            return new LogbookLocation(
                $type,
                $row[$p . '_ref_id'],
                (string) $row[$p . '_label'],
                $row[$p . '_address'],
                $row[$p . '_latitude'] !== null ? (float) $row[$p . '_latitude'] : null,
                $row[$p . '_longitude'] !== null ? (float) $row[$p . '_longitude'] : null,
            );
        };
        $time = static fn (?string $t): ?string => $t !== null ? substr($t, 0, 5) : null;

        return new LogbookEntry(
            id: $row['id'],
            tripDate: new DateTimeImmutable($row['trip_date']),
            departTime: $time($row['depart_time']),
            arriveTime: $time($row['arrive_time']),
            driverUserId: $row['driver_user_id'],
            driverName: $row['driver_name'],
            driverPhone: $row['driver_phone'],
            isExternal: (bool) $row['is_external'],
            vehicleId: $row['vehicle_id'],
            otherVehicleLabel: $row['other_vehicle_label'],
            trailerId: $row['trailer_id'],
            category: LogbookCategory::tryFrom((string) $row['category']) ?? LogbookCategory::Unclear,
            rentalId: $row['rental_id'],
            tourStopId: $row['tour_stop_id'],
            purpose: $row['purpose'],
            from: $location($row, 'from'),
            to: $location($row, 'to'),
            roundTrip: (bool) $row['round_trip'],
            distanceKm: (int) $row['distance_km'],
            odometerStart: $row['odometer_start'] !== null ? (int) $row['odometer_start'] : null,
            odometerEnd: $row['odometer_end'] !== null ? (int) $row['odometer_end'] : null,
            notBilled: (bool) $row['not_billed'],
            status: LogbookStatus::tryFrom((string) $row['status']) ?? LogbookStatus::Approved,
            odometerCheckBypassed: (bool) $row['odometer_check_bypassed'],
            createdByUserId: $row['created_by_user_id'],
            createdAt: new DateTimeImmutable($row['created_at']),
            updatedAt: new DateTimeImmutable($row['updated_at']),
            deletedAt: $row['deleted_at'] !== null ? new DateTimeImmutable($row['deleted_at']) : null,
            deletedByUserId: $row['deleted_by_user_id'],
        );
    }
}
