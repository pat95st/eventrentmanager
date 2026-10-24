<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Rma\Rma;
use App\Domain\Rma\RmaRepositoryInterface;
use DateTimeImmutable;
use PDO;

final class MariadbRmaRepository implements RmaRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function save(Rma $rma): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO rmas
                (id, inventory_number_id, kind, reason, service_provider, rma_reference, sent_at, returned_at, notes, worked_hours, created_at, updated_at)
             VALUES
                (:id, :inventory_number_id, :kind, :reason, :service_provider, :rma_reference, :sent_at, :returned_at, :notes, :worked_hours, :created_at, :updated_at)
             ON DUPLICATE KEY UPDATE
                kind = :kind2, reason = :reason2, service_provider = :service_provider2, rma_reference = :rma_reference2,
                sent_at = :sent_at2, returned_at = :returned_at2, notes = :notes2, worked_hours = :worked_hours2, updated_at = :updated_at2'
        );

        $sentAt = $rma->sentAt?->format('Y-m-d');
        $returnedAt = $rma->returnedAt?->format('Y-m-d');

        $stmt->execute([
            'id' => $rma->id,
            'inventory_number_id' => $rma->inventoryNumberId,
            'kind' => $rma->kind->value,
            'reason' => $rma->reason,
            'service_provider' => $rma->serviceProvider,
            'rma_reference' => $rma->rmaReference,
            'sent_at' => $sentAt,
            'returned_at' => $returnedAt,
            'notes' => $rma->notes,
            'worked_hours' => $rma->workedHours,
            'created_at' => $rma->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $rma->updatedAt->format('Y-m-d H:i:s'),
            'kind2' => $rma->kind->value,
            'reason2' => $rma->reason,
            'service_provider2' => $rma->serviceProvider,
            'rma_reference2' => $rma->rmaReference,
            'sent_at2' => $sentAt,
            'returned_at2' => $returnedAt,
            'notes2' => $rma->notes,
            'worked_hours2' => $rma->workedHours,
            'updated_at2' => $rma->updatedAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function findById(string $id): ?Rma
    {
        $stmt = $this->pdo->prepare('SELECT * FROM rmas WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findByInventoryNumber(string $inventoryNumberId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM rmas WHERE inventory_number_id = :inventory_number_id
             ORDER BY COALESCE(sent_at, created_at) DESC, created_at DESC'
        );
        $stmt->execute(['inventory_number_id' => $inventoryNumberId]);

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function findAll(): array
    {
        $stmt = $this->pdo->query(
            'SELECT * FROM rmas ORDER BY COALESCE(sent_at, created_at) DESC, created_at DESC'
        );

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function countByInventoryNumber(string $inventoryNumberId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM rmas WHERE inventory_number_id = :inventory_number_id');
        $stmt->execute(['inventory_number_id' => $inventoryNumberId]);

        return (int) $stmt->fetchColumn();
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM rmas WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    private function hydrate(array $row): Rma
    {
        return new Rma(
            id: $row['id'],
            inventoryNumberId: $row['inventory_number_id'],
            kind: \App\Domain\Rma\RmaKind::from($row['kind']),
            reason: $row['reason'],
            serviceProvider: $row['service_provider'],
            rmaReference: $row['rma_reference'],
            sentAt: $row['sent_at'] !== null ? new DateTimeImmutable($row['sent_at']) : null,
            returnedAt: $row['returned_at'] !== null ? new DateTimeImmutable($row['returned_at']) : null,
            notes: $row['notes'],
            workedHours: $row['worked_hours'] !== null ? (string) $row['worked_hours'] : null,
            createdAt: new DateTimeImmutable($row['created_at']),
            updatedAt: new DateTimeImmutable($row['updated_at']),
        );
    }
}
