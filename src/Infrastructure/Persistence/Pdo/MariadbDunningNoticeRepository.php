<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Dunning\DunningFeeType;
use App\Domain\Dunning\DunningNotice;
use App\Domain\Dunning\DunningNoticeRepositoryInterface;
use DateTimeImmutable;
use PDO;

final class MariadbDunningNoticeRepository implements DunningNoticeRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findById(string $id): ?DunningNotice
    {
        $stmt = $this->pdo->prepare('SELECT * FROM dunning_notices WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findByInvoice(string $invoiceId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM dunning_notices WHERE invoice_id = :invoice_id ORDER BY created_at DESC');
        $stmt->execute(['invoice_id' => $invoiceId]);

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function existsForInvoiceAndLevel(string $invoiceId, string $dunningLevelId): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM dunning_notices WHERE invoice_id = :invoice_id AND dunning_level_id = :level_id');
        $stmt->execute(['invoice_id' => $invoiceId, 'level_id' => $dunningLevelId]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function save(DunningNotice $notice): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO dunning_notices (
                id, invoice_id, dunning_number, dunning_level_id, level_name_snapshot, days_after_due_snapshot,
                fee_type_snapshot, fee_value_snapshot, fee_amount_snapshot, invoice_gross_total_snapshot,
                created_at, sent_at, created_by
             ) VALUES (
                :id, :invoice_id, :dunning_number, :dunning_level_id, :level_name_snapshot, :days_after_due_snapshot,
                :fee_type_snapshot, :fee_value_snapshot, :fee_amount_snapshot, :invoice_gross_total_snapshot,
                :created_at, :sent_at, :created_by
             )'
        );

        $stmt->execute([
            'id' => $notice->id,
            'invoice_id' => $notice->invoiceId,
            'dunning_number' => $notice->dunningNumber,
            'dunning_level_id' => $notice->dunningLevelId,
            'level_name_snapshot' => $notice->levelName,
            'days_after_due_snapshot' => $notice->daysAfterDueSnapshot,
            'fee_type_snapshot' => $notice->feeType->value,
            'fee_value_snapshot' => $notice->feeValue,
            'fee_amount_snapshot' => $notice->feeAmount,
            'invoice_gross_total_snapshot' => $notice->invoiceGrossTotal,
            'created_at' => $notice->createdAt->format('Y-m-d H:i:s'),
            'sent_at' => $notice->sentAt?->format('Y-m-d H:i:s'),
            'created_by' => $notice->createdBy,
        ]);
    }

    public function markSent(string $id, DateTimeImmutable $sentAt): void
    {
        $stmt = $this->pdo->prepare('UPDATE dunning_notices SET sent_at = :sent_at WHERE id = :id');
        $stmt->execute(['sent_at' => $sentAt->format('Y-m-d H:i:s'), 'id' => $id]);
    }

    private function hydrate(array $row): DunningNotice
    {
        return new DunningNotice(
            id: $row['id'],
            invoiceId: $row['invoice_id'],
            dunningNumber: $row['dunning_number'],
            dunningLevelId: $row['dunning_level_id'],
            levelName: $row['level_name_snapshot'],
            daysAfterDueSnapshot: (int) $row['days_after_due_snapshot'],
            feeType: DunningFeeType::from($row['fee_type_snapshot']),
            feeValue: $row['fee_value_snapshot'],
            feeAmount: $row['fee_amount_snapshot'],
            invoiceGrossTotal: $row['invoice_gross_total_snapshot'],
            createdAt: new DateTimeImmutable($row['created_at']),
            sentAt: $row['sent_at'] !== null ? new DateTimeImmutable($row['sent_at']) : null,
            createdBy: $row['created_by'],
        );
    }
}
