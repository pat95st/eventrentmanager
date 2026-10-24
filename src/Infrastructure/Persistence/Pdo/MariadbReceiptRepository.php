<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Receipt\Receipt;
use App\Domain\Receipt\ReceiptCategory;
use App\Domain\Receipt\ReceiptRepositoryInterface;
use App\Domain\Receipt\ReceiptStatus;
use DateTimeImmutable;
use PDO;

final class MariadbReceiptRepository implements ReceiptRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function save(Receipt $receipt): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO receipts (
                id, supplier_id, receipt_number, amount, receipt_date, category, status, paid_at, note,
                original_filename, stored_filename, mime_type, size_bytes, created_at, updated_at
             ) VALUES (
                :id, :supplier_id, :receipt_number, :amount, :receipt_date, :category, :status, :paid_at, :note,
                :original_filename, :stored_filename, :mime_type, :size_bytes, :created_at, :updated_at
             )
             ON DUPLICATE KEY UPDATE
                supplier_id = :supplier_id2, receipt_number = :receipt_number2, amount = :amount2,
                receipt_date = :receipt_date2, category = :category2, status = :status2, paid_at = :paid_at2,
                note = :note2, original_filename = :original_filename2, stored_filename = :stored_filename2,
                mime_type = :mime_type2, size_bytes = :size_bytes2, updated_at = :updated_at2'
        );

        $paidAt = $receipt->paidAt?->format('Y-m-d H:i:s');

        $stmt->execute([
            'id' => $receipt->id,
            'supplier_id' => $receipt->supplierId,
            'receipt_number' => $receipt->receiptNumber,
            'amount' => $receipt->amount,
            'receipt_date' => $receipt->receiptDate->format('Y-m-d'),
            'category' => $receipt->category->value,
            'status' => $receipt->status->value,
            'paid_at' => $paidAt,
            'note' => $receipt->note,
            'original_filename' => $receipt->originalFilename,
            'stored_filename' => $receipt->storedFilename,
            'mime_type' => $receipt->mimeType,
            'size_bytes' => $receipt->sizeBytes,
            'created_at' => $receipt->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $receipt->updatedAt->format('Y-m-d H:i:s'),
            'supplier_id2' => $receipt->supplierId,
            'receipt_number2' => $receipt->receiptNumber,
            'amount2' => $receipt->amount,
            'receipt_date2' => $receipt->receiptDate->format('Y-m-d'),
            'category2' => $receipt->category->value,
            'status2' => $receipt->status->value,
            'paid_at2' => $paidAt,
            'note2' => $receipt->note,
            'original_filename2' => $receipt->originalFilename,
            'stored_filename2' => $receipt->storedFilename,
            'mime_type2' => $receipt->mimeType,
            'size_bytes2' => $receipt->sizeBytes,
            'updated_at2' => $receipt->updatedAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function findById(string $id): ?Receipt
    {
        $stmt = $this->pdo->prepare('SELECT * FROM receipts WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $placeholders = [];
        $params = [];
        foreach (array_values($ids) as $index => $id) {
            $placeholder = 'id' . $index;
            $placeholders[] = ':' . $placeholder;
            $params[$placeholder] = $id;
        }

        $stmt = $this->pdo->prepare(
            'SELECT * FROM receipts WHERE id IN (' . implode(', ', $placeholders) . ')
             ORDER BY receipt_date DESC, created_at DESC'
        );
        $stmt->execute($params);

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function search(
        ?string $searchTerm,
        ?string $supplierId,
        ?ReceiptStatus $status,
        ?ReceiptCategory $category,
        int $limit,
        int $offset,
    ): array {
        [$where, $params] = $this->buildSearchWhere($searchTerm, $supplierId, $status, $category);

        $sql = 'SELECT r.* FROM receipts r LEFT JOIN suppliers s ON s.id = r.supplier_id'
            . $where . ' ORDER BY r.receipt_date DESC, r.created_at DESC LIMIT :limit OFFSET :offset';

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue('limit', max(0, $limit), PDO::PARAM_INT);
        $stmt->bindValue('offset', max(0, $offset), PDO::PARAM_INT);
        $stmt->execute();

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function countSearch(
        ?string $searchTerm,
        ?string $supplierId,
        ?ReceiptStatus $status,
        ?ReceiptCategory $category,
    ): int {
        [$where, $params] = $this->buildSearchWhere($searchTerm, $supplierId, $status, $category);

        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM receipts r LEFT JOIN suppliers s ON s.id = r.supplier_id' . $where);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /** @return array{0: string, 1: array<string, string>} */
    private function buildSearchWhere(
        ?string $searchTerm,
        ?string $supplierId,
        ?ReceiptStatus $status,
        ?ReceiptCategory $category,
    ): array {
        $sql = ' WHERE 1 = 1';
        $params = [];

        if ($searchTerm !== null && trim($searchTerm) !== '') {
            $sql .= ' AND (r.receipt_number LIKE :search OR r.note LIKE :search2 OR s.name LIKE :search3)';
            $needle = '%' . $searchTerm . '%';
            $params['search'] = $needle;
            $params['search2'] = $needle;
            $params['search3'] = $needle;
        }

        if ($supplierId !== null && $supplierId !== '') {
            $sql .= ' AND r.supplier_id = :supplier_id';
            $params['supplier_id'] = $supplierId;
        }

        if ($status !== null) {
            $sql .= ' AND r.status = :status';
            $params['status'] = $status->value;
        }

        if ($category !== null) {
            $sql .= ' AND r.category = :category';
            $params['category'] = $category->value;
        }

        return [$sql, $params];
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM receipts WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public function findPaidBetween(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM receipts WHERE status = 'bezahlt' AND paid_at IS NOT NULL
             AND paid_at >= :from AND paid_at <= :to ORDER BY paid_at ASC"
        );
        $stmt->execute([
            'from' => $from->format('Y-m-d 00:00:00'),
            'to' => $to->format('Y-m-d 23:59:59'),
        ]);

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    private function hydrate(array $row): Receipt
    {
        return new Receipt(
            id: $row['id'],
            supplierId: $row['supplier_id'],
            receiptNumber: $row['receipt_number'],
            amount: $row['amount'],
            receiptDate: new DateTimeImmutable($row['receipt_date']),
            category: ReceiptCategory::from($row['category']),
            status: ReceiptStatus::from($row['status']),
            paidAt: $row['paid_at'] !== null ? new DateTimeImmutable($row['paid_at']) : null,
            note: $row['note'],
            originalFilename: $row['original_filename'],
            storedFilename: $row['stored_filename'],
            mimeType: $row['mime_type'],
            sizeBytes: (int) $row['size_bytes'],
            createdAt: new DateTimeImmutable($row['created_at']),
            updatedAt: new DateTimeImmutable($row['updated_at']),
        );
    }
}
