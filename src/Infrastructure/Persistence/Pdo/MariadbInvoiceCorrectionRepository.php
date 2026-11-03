<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\InvoiceCorrection\InvoiceCorrection;
use App\Domain\InvoiceCorrection\InvoiceCorrectionLine;
use App\Domain\InvoiceCorrection\InvoiceCorrectionRepositoryInterface;
use App\Domain\InvoiceCorrection\InvoiceCorrectionStatus;
use App\Domain\InvoiceCorrection\InvoiceCorrectionType;
use DateTimeImmutable;
use PDO;

/** Rechnungskorrekturen (Migration 121). */
final class MariadbInvoiceCorrectionRepository implements InvoiceCorrectionRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function save(InvoiceCorrection $correction): void
    {
        $ownTransaction = !$this->pdo->inTransaction();
        if ($ownTransaction) {
            $this->pdo->beginTransaction();
        }
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO invoice_corrections
                    (id, invoice_id, correction_type, status, correction_number, correction_date, reason, note,
                     is_small_business, vat_rate_percent, tax_amount_fixed, requires_settlement, settled_at, created_at, updated_at)
                 VALUES
                    (:id, :invoice_id, :correction_type, :status, :correction_number, :correction_date, :reason, :note,
                     :is_small_business, :vat_rate_percent, :tax_amount_fixed, :requires_settlement, :settled_at, :created_at, :updated_at)
                 ON DUPLICATE KEY UPDATE
                    status = VALUES(status), correction_number = VALUES(correction_number),
                    correction_date = VALUES(correction_date), reason = VALUES(reason), note = VALUES(note),
                    is_small_business = VALUES(is_small_business), vat_rate_percent = VALUES(vat_rate_percent),
                    tax_amount_fixed = VALUES(tax_amount_fixed),
                    requires_settlement = VALUES(requires_settlement), settled_at = VALUES(settled_at),
                    updated_at = VALUES(updated_at)'
            );
            $stmt->execute([
                'id' => $correction->id,
                'invoice_id' => $correction->invoiceId,
                'correction_type' => $correction->type->value,
                'status' => $correction->status->value,
                'correction_number' => $correction->correctionNumber,
                'correction_date' => $correction->correctionDate?->format('Y-m-d'),
                'reason' => $correction->reason,
                'note' => $correction->note,
                'is_small_business' => $correction->isSmallBusiness ? 1 : 0,
                'vat_rate_percent' => $correction->vatRatePercent,
                'tax_amount_fixed' => $correction->taxAmountFixed,
                'requires_settlement' => $correction->requiresSettlement ? 1 : 0,
                'settled_at' => $correction->settledAt?->format('Y-m-d'),
                'created_at' => $correction->createdAt->format('Y-m-d H:i:s'),
                'updated_at' => $correction->updatedAt->format('Y-m-d H:i:s'),
            ]);

            $this->pdo->prepare('DELETE FROM invoice_correction_lines WHERE correction_id = :id')->execute(['id' => $correction->id]);
            $insert = $this->pdo->prepare(
                'INSERT INTO invoice_correction_lines (id, correction_id, sort_order, description, quantity, unit_price)
                 VALUES (:id, :correction_id, :sort_order, :description, :quantity, :unit_price)'
            );
            foreach ($correction->lines as $line) {
                $insert->execute([
                    'id' => $line->id,
                    'correction_id' => $correction->id,
                    'sort_order' => $line->sortOrder,
                    'description' => $line->description,
                    'quantity' => $line->quantity,
                    'unit_price' => $line->unitPrice,
                ]);
            }

            if ($ownTransaction) {
                $this->pdo->commit();
            }
        } catch (\Throwable $e) {
            if ($ownTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function findById(string $id): ?InvoiceCorrection
    {
        $stmt = $this->pdo->prepare('SELECT * FROM invoice_corrections WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->hydrateAll([$row])[0] : null;
    }

    public function findByInvoice(string $invoiceId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM invoice_corrections WHERE invoice_id = :invoice_id ORDER BY created_at DESC');
        $stmt->execute(['invoice_id' => $invoiceId]);

        return $this->hydrateAll($stmt->fetchAll());
    }

    public function findAll(?string $search = null): array
    {
        $sql = 'SELECT c.* FROM invoice_corrections c JOIN invoices i ON i.id = c.invoice_id';
        $params = [];
        $search = $search !== null ? trim($search) : '';
        if ($search !== '') {
            $sql .= ' WHERE c.correction_number LIKE :q1 OR i.invoice_number LIKE :q2 OR c.reason LIKE :q3';
            $like = '%' . addcslashes($search, '%_\\') . '%';
            $params = ['q1' => $like, 'q2' => $like, 'q3' => $like];
        }
        $sql .= ' ORDER BY c.created_at DESC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $this->hydrateAll($stmt->fetchAll());
    }

    public function findIssued(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM invoice_corrections WHERE status = 'issued' ORDER BY correction_date ASC");

        return $this->hydrateAll($stmt->fetchAll());
    }

    public function markSent(string $id, DateTimeImmutable $sentAt, string $recipient): void
    {
        $this->pdo->prepare('UPDATE invoice_corrections SET sent_at = :sent_at, sent_to = :sent_to WHERE id = :id')
            ->execute(['sent_at' => $sentAt->format('Y-m-d H:i:s'), 'sent_to' => mb_substr($recipient, 0, 255), 'id' => $id]);
    }

    public function delete(string $id): void
    {
        $this->pdo->prepare('DELETE FROM invoice_corrections WHERE id = :id')->execute(['id' => $id]);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return InvoiceCorrection[]
     */
    private function hydrateAll(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $ids = array_map(static fn (array $row): string => (string) $row['id'], $rows);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare("SELECT * FROM invoice_correction_lines WHERE correction_id IN ($placeholders) ORDER BY sort_order ASC");
        $stmt->execute($ids);
        $linesByCorrection = [];
        foreach ($stmt->fetchAll() as $line) {
            $linesByCorrection[$line['correction_id']][] = new InvoiceCorrectionLine(
                id: $line['id'],
                correctionId: $line['correction_id'],
                sortOrder: (int) $line['sort_order'],
                description: $line['description'],
                quantity: (string) $line['quantity'],
                unitPrice: (string) $line['unit_price'],
            );
        }

        return array_map(fn (array $row): InvoiceCorrection => new InvoiceCorrection(
            id: $row['id'],
            invoiceId: $row['invoice_id'],
            type: InvoiceCorrectionType::from($row['correction_type']),
            status: InvoiceCorrectionStatus::from($row['status']),
            correctionNumber: $row['correction_number'],
            correctionDate: $row['correction_date'] !== null ? new DateTimeImmutable($row['correction_date']) : null,
            reason: $row['reason'],
            note: $row['note'],
            isSmallBusiness: (bool) $row['is_small_business'],
            vatRatePercent: (string) $row['vat_rate_percent'],
            requiresSettlement: (bool) $row['requires_settlement'],
            settledAt: $row['settled_at'] !== null ? new DateTimeImmutable($row['settled_at']) : null,
            lines: $linesByCorrection[$row['id']] ?? [],
            createdAt: new DateTimeImmutable($row['created_at']),
            updatedAt: new DateTimeImmutable($row['updated_at']),
            taxAmountFixed: $row['tax_amount_fixed'] !== null ? (string) $row['tax_amount_fixed'] : null,
            sentAt: ($row['sent_at'] ?? null) !== null ? new DateTimeImmutable($row['sent_at']) : null,
            sentTo: $row['sent_to'] ?? null,
        ), $rows);
    }
}
