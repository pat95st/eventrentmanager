<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Rental\Rental;
use App\Domain\Rental\RentalRepositoryInterface;
use DateTimeImmutable;
use PDO;

final class MariadbRentalRepository implements RentalRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function save(Rental $rental): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO rentals (
                id, customer_id, start_date, end_date, event_name, event_location, venue_id,
                pickup_warehouse_id, pickup_at, return_warehouse_id, return_at, transport_note,
                deployment_days, created_at, updated_at
             ) VALUES (
                :id, :customer_id, :start_date, :end_date, :event_name, :event_location, :venue_id,
                :pickup_warehouse_id, :pickup_at, :return_warehouse_id, :return_at, :transport_note,
                :deployment_days, :created_at, :updated_at
             )
             ON DUPLICATE KEY UPDATE
                customer_id = :customer_id2, start_date = :start_date2, end_date = :end_date2,
                event_name = :event_name2, event_location = :event_location2, venue_id = :venue_id2,
                pickup_warehouse_id = :pickup_warehouse_id2, pickup_at = :pickup_at2,
                return_warehouse_id = :return_warehouse_id2, return_at = :return_at2,
                transport_note = :transport_note2,
                deployment_days = :deployment_days2, updated_at = :updated_at2'
        );

        $stmt->execute([
            'id' => $rental->id,
            'customer_id' => $rental->customerId,
            'start_date' => $rental->startDate->format('Y-m-d'),
            'end_date' => $rental->endDate->format('Y-m-d'),
            'event_name' => $rental->eventName,
            'event_location' => $rental->eventLocation,
            'venue_id' => $rental->venueId,
            'pickup_warehouse_id' => $rental->pickupWarehouseId,
            'pickup_at' => $rental->pickupAt?->format('Y-m-d H:i:s'),
            'return_warehouse_id' => $rental->returnWarehouseId,
            'return_at' => $rental->returnAt?->format('Y-m-d H:i:s'),
            'transport_note' => $rental->transportNote,
            'deployment_days' => $rental->deploymentDays,
            'created_at' => $rental->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $rental->updatedAt->format('Y-m-d H:i:s'),
            'customer_id2' => $rental->customerId,
            'start_date2' => $rental->startDate->format('Y-m-d'),
            'end_date2' => $rental->endDate->format('Y-m-d'),
            'event_name2' => $rental->eventName,
            'event_location2' => $rental->eventLocation,
            'venue_id2' => $rental->venueId,
            'pickup_warehouse_id2' => $rental->pickupWarehouseId,
            'pickup_at2' => $rental->pickupAt?->format('Y-m-d H:i:s'),
            'return_warehouse_id2' => $rental->returnWarehouseId,
            'return_at2' => $rental->returnAt?->format('Y-m-d H:i:s'),
            'transport_note2' => $rental->transportNote,
            'deployment_days2' => $rental->deploymentDays,
            'updated_at2' => $rental->updatedAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function findById(string $id): ?Rental
    {
        $stmt = $this->pdo->prepare('SELECT * FROM rentals WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function search(?string $searchTerm, ?string $customerId, int $limit, int $offset): array
    {
        [$where, $params] = $this->buildSearchWhere($searchTerm, $customerId);

        $sql = 'SELECT * FROM rentals' . $where . ' ORDER BY start_date DESC LIMIT :limit OFFSET :offset';

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue('limit', max(0, $limit), PDO::PARAM_INT);
        $stmt->bindValue('offset', max(0, $offset), PDO::PARAM_INT);
        $stmt->execute();

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function countSearch(?string $searchTerm, ?string $customerId): int
    {
        [$where, $params] = $this->buildSearchWhere($searchTerm, $customerId);

        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM rentals' . $where);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /** @return array{0: string, 1: array<string, string>} [WHERE-Klausel (inkl. führendem Leerzeichen), Parameter] */
    private function buildSearchWhere(?string $searchTerm, ?string $customerId): array
    {
        $sql = ' WHERE 1 = 1';
        $params = [];

        if ($searchTerm !== null && trim($searchTerm) !== '') {
            $sql .= ' AND (event_name LIKE :search OR event_location LIKE :search2)';
            $needle = '%' . $searchTerm . '%';
            $params['search'] = $needle;
            $params['search2'] = $needle;
        }

        if ($customerId !== null && $customerId !== '') {
            $sql .= ' AND customer_id = :customer_id';
            $params['customer_id'] = $customerId;
        }

        return [$sql, $params];
    }

    public function findOverlapping(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM rentals WHERE start_date <= :to AND end_date >= :from ORDER BY start_date ASC'
        );
        $stmt->execute([
            'from' => $from->format('Y-m-d'),
            'to' => $to->format('Y-m-d'),
        ]);

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function findByVenue(string $venueId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM rentals WHERE venue_id = :venue_id ORDER BY start_date DESC');
        $stmt->execute(['venue_id' => $venueId]);

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM rentals WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public function searchForCalendar(
        array $categoryIds,
        ?string $manufacturerId,
        ?string $warehouseId,
        ?string $venueId,
        ?DateTimeImmutable $rangeStart,
        ?DateTimeImmutable $rangeEnd,
    ): array {
        // JOIN auf rental_items/articles nur, wenn tatsächlich nach
        // Kategorie/Hersteller gefiltert wird (DISTINCT nötig, da eine
        // Vermietung mehrere passende Artikel enthalten kann).
        $needsArticleJoin = $categoryIds !== [] || $manufacturerId !== null;
        $sql = $needsArticleJoin
            ? 'SELECT DISTINCT r.* FROM rentals r
               INNER JOIN rental_items ri ON ri.rental_id = r.id
               INNER JOIN articles a ON a.id = ri.article_id'
            : 'SELECT r.* FROM rentals r';

        $sql .= ' WHERE 1 = 1';
        $params = [];

        if ($categoryIds !== []) {
            $placeholders = [];
            foreach ($categoryIds as $index => $categoryId) {
                $key = 'category_id_' . $index;
                $placeholders[] = ':' . $key;
                $params[$key] = $categoryId;
            }
            $sql .= ' AND a.category_id IN (' . implode(', ', $placeholders) . ')';
        }

        if ($manufacturerId !== null) {
            $sql .= ' AND a.manufacturer_id = :manufacturer_id';
            $params['manufacturer_id'] = $manufacturerId;
        }

        if ($warehouseId !== null) {
            $sql .= ' AND (r.pickup_warehouse_id = :warehouse_id OR r.return_warehouse_id = :warehouse_id2)';
            $params['warehouse_id'] = $warehouseId;
            $params['warehouse_id2'] = $warehouseId;
        }

        if ($venueId !== null) {
            $sql .= ' AND r.venue_id = :venue_id';
            $params['venue_id'] = $venueId;
        }

        if ($rangeStart !== null) {
            $sql .= ' AND r.end_date >= :range_start';
            $params['range_start'] = $rangeStart->format('Y-m-d');
        }

        if ($rangeEnd !== null) {
            $sql .= ' AND r.start_date <= :range_end';
            $params['range_end'] = $rangeEnd->format('Y-m-d');
        }

        $sql .= ' ORDER BY r.start_date ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function findPickupsDueForReminder(DateTimeImmutable $now, int $leadHours): array
    {
        return $this->findDueForReminder('pickup_at', 'pickup', $now, $leadHours);
    }

    public function findReturnsDueForReminder(DateTimeImmutable $now, int $leadHours): array
    {
        return $this->findDueForReminder('return_at', 'return', $now, $leadHours);
    }

    public function findChecklistsDueForReminder(DateTimeImmutable $now, int $leadHours): array
    {
        return $this->findDueForReminder('return_at', 'customer_checklist', $now, $leadHours);
    }

    /**
     * Gemeinsame Implementierung für findPickupsDueForReminder()/
     * findReturnsDueForReminder() — $timestampColumn ist entweder
     * 'pickup_at' oder 'return_at', $reminderType der dazu passende
     * RentalReminderType::value ('pickup'/'return'). NOT EXISTS statt eines
     * Joins, damit die Duplikat-Prüfung unabhängig von der konkreten
     * rental_reminders-Zeile bleibt (nur EIN Eintrag pro Vermietung+Art
     * durch den UNIQUE-Constraint möglich, siehe Migration 067).
     *
     * @return Rental[]
     */
    private function findDueForReminder(string $timestampColumn, string $reminderType, DateTimeImmutable $now, int $leadHours): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT r.* FROM rentals r
             WHERE r.{$timestampColumn} IS NOT NULL
               AND r.{$timestampColumn} > :now
               AND r.{$timestampColumn} <= :deadline
               AND NOT EXISTS (
                   SELECT 1 FROM rental_reminders rr
                   WHERE rr.rental_id = r.id AND rr.type = :reminder_type
               )
             ORDER BY r.{$timestampColumn} ASC"
        );

        $deadline = $now->modify("+{$leadHours} hours");

        $stmt->execute([
            'now' => $now->format('Y-m-d H:i:s'),
            'deadline' => $deadline->format('Y-m-d H:i:s'),
            'reminder_type' => $reminderType,
        ]);

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    private function hydrate(array $row): Rental
    {
        return new Rental(
            id: $row['id'],
            customerId: $row['customer_id'],
            startDate: new DateTimeImmutable($row['start_date']),
            endDate: new DateTimeImmutable($row['end_date']),
            eventName: $row['event_name'],
            eventLocation: $row['event_location'],
            venueId: $row['venue_id'],
            deploymentDays: (int) $row['deployment_days'],
            createdAt: new DateTimeImmutable($row['created_at']),
            updatedAt: new DateTimeImmutable($row['updated_at']),
            pickupWarehouseId: $row['pickup_warehouse_id'],
            pickupAt: $row['pickup_at'] !== null ? new DateTimeImmutable($row['pickup_at']) : null,
            returnWarehouseId: $row['return_warehouse_id'],
            returnAt: $row['return_at'] !== null ? new DateTimeImmutable($row['return_at']) : null,
            transportNote: $row['transport_note'],
        );
    }
}
