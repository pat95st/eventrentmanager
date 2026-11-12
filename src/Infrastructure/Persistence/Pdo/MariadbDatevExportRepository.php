<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Datev\DatevExport;
use App\Domain\Datev\DatevExportRepositoryInterface;
use App\Domain\Datev\DatevExportTrigger;
use DateTimeImmutable;
use PDO;

final class MariadbDatevExportRepository implements DatevExportRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function save(DatevExport $export): void
    {
        $this->pdo->prepare(
            'INSERT INTO datev_exports (id, period_from, period_to, export_trigger, recipient, booking_count, document_count, created_at)
             VALUES (:id, :period_from, :period_to, :export_trigger, :recipient, :booking_count, :document_count, :created_at)'
        )->execute([
            'id' => $export->id,
            'period_from' => $export->periodFrom->format('Y-m-d'),
            'period_to' => $export->periodTo->format('Y-m-d'),
            'export_trigger' => $export->trigger->value,
            'recipient' => $export->recipient !== null ? mb_substr($export->recipient, 0, 255) : null,
            'booking_count' => $export->bookingCount,
            'document_count' => $export->documentCount,
            'created_at' => $export->createdAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function findRecent(int $limit): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM datev_exports ORDER BY created_at DESC LIMIT :limit');
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return array_map(static fn (array $row) => new DatevExport(
            id: $row['id'],
            periodFrom: new DateTimeImmutable($row['period_from']),
            periodTo: new DateTimeImmutable($row['period_to']),
            trigger: DatevExportTrigger::tryFrom($row['export_trigger']) ?? DatevExportTrigger::Download,
            recipient: $row['recipient'],
            bookingCount: (int) $row['booking_count'],
            documentCount: (int) $row['document_count'],
            createdAt: new DateTimeImmutable($row['created_at']),
        ), $stmt->fetchAll());
    }

    public function existsAutoSentForPeriod(DateTimeImmutable $from, DateTimeImmutable $to): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM datev_exports WHERE export_trigger = :trigger AND period_from = :from AND period_to = :to LIMIT 1'
        );
        $stmt->execute([
            'trigger' => DatevExportTrigger::AutoEmail->value,
            'from' => $from->format('Y-m-d'),
            'to' => $to->format('Y-m-d'),
        ]);

        return $stmt->fetchColumn() !== false;
    }
}
