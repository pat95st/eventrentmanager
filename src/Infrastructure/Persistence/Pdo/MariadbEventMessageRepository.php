<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\EventMessage\EventMessage;
use App\Domain\EventMessage\EventMessageRepositoryInterface;
use DateTimeImmutable;
use PDO;

final class MariadbEventMessageRepository implements EventMessageRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function save(EventMessage $m): void
    {
        $this->pdo->prepare(
            'INSERT INTO event_messages (id, rental_id, minute_program_id, body, to_stage, to_foh, urgent, sent_by_type, sent_by_id, sent_by_name, created_at, acknowledged_at, acknowledged_by, closed_at, closed_by)
             VALUES (:id, :rental, :program, :body, :stage, :foh, :urgent, :btype, :bid, :bname, :created, :ack, :ackby, :closed, :closedby)
             ON DUPLICATE KEY UPDATE acknowledged_at = VALUES(acknowledged_at), acknowledged_by = VALUES(acknowledged_by),
                closed_at = VALUES(closed_at), closed_by = VALUES(closed_by)'
        )->execute([
            'id' => $m->id,
            'rental' => $m->rentalId,
            'program' => $m->minuteProgramId,
            'body' => $m->body,
            'stage' => $m->toStage ? 1 : 0,
            'foh' => $m->toFoh ? 1 : 0,
            'urgent' => $m->urgent ? 1 : 0,
            'btype' => $m->sentByType,
            'bid' => $m->sentById,
            'bname' => $m->sentByName,
            'created' => $m->createdAt->format('Y-m-d H:i:s'),
            'ack' => $m->acknowledgedAt?->format('Y-m-d H:i:s'),
            'ackby' => $m->acknowledgedBy,
            'closed' => $m->closedAt?->format('Y-m-d H:i:s'),
            'closedby' => $m->closedBy,
        ]);
    }

    public function findById(string $id): ?EventMessage
    {
        $stmt = $this->pdo->prepare('SELECT * FROM event_messages WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row !== false ? $this->hydrate($row) : null;
    }

    public function findActiveByRental(string $rentalId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM event_messages WHERE rental_id = :id AND closed_at IS NULL ORDER BY created_at, id');
        $stmt->execute(['id' => $rentalId]);

        return array_map(fn (array $r) => $this->hydrate($r), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function findRecentByRental(string $rentalId, int $closedLimit = 10): array
    {
        $active = array_reverse($this->findActiveByRental($rentalId));
        $stmt = $this->pdo->prepare('SELECT * FROM event_messages WHERE rental_id = :id AND closed_at IS NOT NULL ORDER BY closed_at DESC, created_at DESC LIMIT ' . max(0, $closedLimit));
        $stmt->execute(['id' => $rentalId]);

        return array_merge($active, array_map(fn (array $r) => $this->hydrate($r), $stmt->fetchAll(PDO::FETCH_ASSOC)));
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): EventMessage
    {
        return new EventMessage(
            id: $row['id'],
            rentalId: $row['rental_id'],
            minuteProgramId: $row['minute_program_id'],
            body: $row['body'],
            toStage: (bool) $row['to_stage'],
            toFoh: (bool) $row['to_foh'],
            urgent: (bool) $row['urgent'],
            sentByType: $row['sent_by_type'],
            sentById: $row['sent_by_id'],
            sentByName: $row['sent_by_name'],
            createdAt: new DateTimeImmutable($row['created_at']),
            acknowledgedAt: $row['acknowledged_at'] !== null ? new DateTimeImmutable($row['acknowledged_at']) : null,
            acknowledgedBy: $row['acknowledged_by'],
            closedAt: $row['closed_at'] !== null ? new DateTimeImmutable($row['closed_at']) : null,
            closedBy: $row['closed_by'],
        );
    }
}
