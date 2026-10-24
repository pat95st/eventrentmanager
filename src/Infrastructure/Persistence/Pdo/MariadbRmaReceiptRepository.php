<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Rma\RmaReceiptRepositoryInterface;
use App\Shared\Uuid;
use DateTimeImmutable;
use PDO;

final class MariadbRmaReceiptRepository implements RmaReceiptRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function replaceForRma(string $rmaId, array $receiptIds): void
    {
        $this->pdo->beginTransaction();

        try {
            $delete = $this->pdo->prepare('DELETE FROM rma_receipts WHERE rma_id = :rma_id');
            $delete->execute(['rma_id' => $rmaId]);

            $insert = $this->pdo->prepare(
                'INSERT INTO rma_receipts (id, rma_id, receipt_id, created_at)
                 VALUES (:id, :rma_id, :receipt_id, :created_at)'
            );

            foreach ($receiptIds as $receiptId) {
                $insert->execute([
                    'id' => Uuid::v4(),
                    'rma_id' => $rmaId,
                    'receipt_id' => $receiptId,
                    'created_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
                ]);
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();

            throw $e;
        }
    }

    /** @return string[] */
    public function findReceiptIdsByRma(string $rmaId): array
    {
        $stmt = $this->pdo->prepare('SELECT receipt_id FROM rma_receipts WHERE rma_id = :rma_id ORDER BY created_at DESC');
        $stmt->execute(['rma_id' => $rmaId]);

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}
