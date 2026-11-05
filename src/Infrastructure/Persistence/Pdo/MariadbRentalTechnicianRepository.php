<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Technician\RentalTechnician;
use App\Domain\Technician\RentalTechnicianRepositoryInterface;
use App\Domain\Technician\RentalTechnicianStatus;
use DateTimeImmutable;
use PDO;

/** Techniker je Veranstaltung (Migration 134). */
final class MariadbRentalTechnicianRepository implements RentalTechnicianRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findById(string $id): ?RentalTechnician
    {
        return $this->one('SELECT * FROM rental_technicians WHERE id = :id', ['id' => $id]);
    }

    public function findByRental(string $rentalId): array
    {
        return $this->many('SELECT * FROM rental_technicians WHERE rental_id = :id ORDER BY created_at', ['id' => $rentalId]);
    }

    public function findByRentalAndUser(string $rentalId, string $userId): ?RentalTechnician
    {
        return $this->one('SELECT * FROM rental_technicians WHERE rental_id = :rental AND user_id = :user', ['rental' => $rentalId, 'user' => $userId]);
    }

    public function findByUser(string $userId): array
    {
        return $this->many('SELECT * FROM rental_technicians WHERE user_id = :id ORDER BY requested_at DESC', ['id' => $userId]);
    }

    public function findOpenRequests(): array
    {
        return $this->many('SELECT * FROM rental_technicians WHERE status = :status ORDER BY requested_at', ['status' => RentalTechnicianStatus::Requested->value]);
    }

    public function save(RentalTechnician $t): void
    {
        $this->pdo->prepare(
            'INSERT INTO rental_technicians (id, rental_id, user_id, status, trade_ids, note, requested_at, responded_at,
                reminder_count, last_notified_at, created_at, updated_at)
             VALUES (:id, :rental, :user, :status, :trades, :note, :requested, :responded, :reminders, :notified, :created, :updated)
             ON DUPLICATE KEY UPDATE status = VALUES(status), trade_ids = VALUES(trade_ids), note = VALUES(note),
                requested_at = VALUES(requested_at), responded_at = VALUES(responded_at), reminder_count = VALUES(reminder_count),
                last_notified_at = VALUES(last_notified_at), updated_at = VALUES(updated_at)'
        )->execute([
            'id' => $t->id,
            'rental' => $t->rentalId,
            'user' => $t->userId,
            'status' => $t->status->value,
            'trades' => $t->tradeIds === [] ? null : implode(',', $t->tradeIds),
            'note' => $t->note,
            'requested' => $t->requestedAt->format('Y-m-d H:i:s'),
            'responded' => $t->respondedAt?->format('Y-m-d H:i:s'),
            'reminders' => $t->reminderCount,
            'notified' => $t->lastNotifiedAt?->format('Y-m-d H:i:s'),
            'created' => $t->createdAt->format('Y-m-d H:i:s'),
            'updated' => $t->updatedAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function delete(string $id): void
    {
        $this->pdo->prepare('DELETE FROM rental_technicians WHERE id = :id')->execute(['id' => $id]);
    }

    /** @param array<string, string> $params */
    private function one(string $sql, array $params): ?RentalTechnician
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    /** @param array<string, string> $params @return RentalTechnician[] */
    private function many(string $sql, array $params): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): RentalTechnician
    {
        return new RentalTechnician(
            id: $row['id'],
            rentalId: $row['rental_id'],
            userId: $row['user_id'],
            status: RentalTechnicianStatus::tryFrom((string) $row['status']) ?? RentalTechnicianStatus::Requested,
            tradeIds: $row['trade_ids'] !== null && $row['trade_ids'] !== '' ? explode(',', (string) $row['trade_ids']) : [],
            note: $row['note'],
            requestedAt: new DateTimeImmutable($row['requested_at']),
            respondedAt: $row['responded_at'] !== null ? new DateTimeImmutable($row['responded_at']) : null,
            reminderCount: (int) $row['reminder_count'],
            lastNotifiedAt: $row['last_notified_at'] !== null ? new DateTimeImmutable($row['last_notified_at']) : null,
            createdAt: new DateTimeImmutable($row['created_at']),
            updatedAt: new DateTimeImmutable($row['updated_at']),
        );
    }
}
