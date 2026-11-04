<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Banking\AccountAmount;
use App\Domain\Banking\AccountLedgerRepositoryInterface;
use App\Domain\Banking\AccountTransaction;
use App\Domain\Banking\AccountTransactionKind;
use DateTimeImmutable;
use PDO;

/** Kontoführung (Migration 131). */
final class MariadbAccountLedgerRepository implements AccountLedgerRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function openingBalance(string $bankAccountId): array
    {
        $stmt = $this->pdo->prepare('SELECT opening_balance, opening_balance_date FROM bank_accounts WHERE id = :id');
        $stmt->execute(['id' => $bankAccountId]);
        $row = $stmt->fetch();

        return [
            'cents' => $row ? AccountAmount::fromDecimal((string) $row['opening_balance']) : 0,
            'date' => $row && $row['opening_balance_date'] !== null ? new DateTimeImmutable($row['opening_balance_date']) : null,
        ];
    }

    public function saveOpeningBalance(string $bankAccountId, int $cents, ?DateTimeImmutable $date): void
    {
        $this->pdo->prepare('UPDATE bank_accounts SET opening_balance = :amount, opening_balance_date = :date WHERE id = :id')
            ->execute(['amount' => AccountAmount::toDecimal($cents), 'date' => $date?->format('Y-m-d'), 'id' => $bankAccountId]);
    }

    public function findBetween(string $bankAccountId, DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM account_transactions WHERE bank_account_id = :account AND booking_date BETWEEN :from AND :to
             ORDER BY booking_date ASC, created_at ASC, id ASC'
        );
        $stmt->execute(['account' => $bankAccountId, 'from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d')]);

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function sumBetween(string $bankAccountId, ?DateTimeImmutable $from, DateTimeImmutable $before): int
    {
        $sql = 'SELECT COALESCE(SUM(amount), 0) FROM account_transactions WHERE bank_account_id = :account AND booking_date < :before';
        $params = ['account' => $bankAccountId, 'before' => $before->format('Y-m-d')];
        if ($from !== null) {
            $sql .= ' AND booking_date >= :from';
            $params['from'] = $from->format('Y-m-d');
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return AccountAmount::fromDecimal((string) $stmt->fetchColumn());
    }

    public function findById(string $id): ?AccountTransaction
    {
        $stmt = $this->pdo->prepare('SELECT * FROM account_transactions WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findByReceipt(string $receiptId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM account_transactions WHERE receipt_id = :id ORDER BY booking_date');
        $stmt->execute(['id' => $receiptId]);

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function findByInvoice(string $invoiceId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM account_transactions WHERE invoice_id = :id ORDER BY booking_date');
        $stmt->execute(['id' => $invoiceId]);

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function importHashExists(string $bankAccountId, string $importHash): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM account_transactions WHERE bank_account_id = :account AND import_hash = :hash LIMIT 1');
        $stmt->execute(['account' => $bankAccountId, 'hash' => $importHash]);

        return $stmt->fetchColumn() !== false;
    }

    public function save(AccountTransaction $transaction): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO account_transactions (id, bank_account_id, booking_date, amount, kind, description, counterparty,
                receipt_id, invoice_id, import_hash, created_at, updated_at)
             VALUES (:id, :account, :date, :amount, :kind, :description, :counterparty, :receipt, :invoice, :hash, :created, :updated)
             ON DUPLICATE KEY UPDATE booking_date = VALUES(booking_date), amount = VALUES(amount), kind = VALUES(kind),
                description = VALUES(description), counterparty = VALUES(counterparty), receipt_id = VALUES(receipt_id),
                invoice_id = VALUES(invoice_id), updated_at = VALUES(updated_at)'
        );
        $stmt->execute([
            'id' => $transaction->id,
            'account' => $transaction->bankAccountId,
            'date' => $transaction->bookingDate->format('Y-m-d'),
            'amount' => AccountAmount::toDecimal($transaction->amountCents),
            'kind' => $transaction->kind->value,
            'description' => mb_substr($transaction->description, 0, 255),
            'counterparty' => $transaction->counterparty !== null ? mb_substr($transaction->counterparty, 0, 255) : null,
            'receipt' => $transaction->receiptId,
            'invoice' => $transaction->invoiceId,
            'hash' => $transaction->importHash,
            'created' => $transaction->createdAt->format('Y-m-d H:i:s'),
            'updated' => $transaction->updatedAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function delete(string $id): void
    {
        $this->pdo->prepare('DELETE FROM account_transactions WHERE id = :id')->execute(['id' => $id]);
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): AccountTransaction
    {
        return new AccountTransaction(
            id: $row['id'],
            bankAccountId: $row['bank_account_id'],
            bookingDate: new DateTimeImmutable($row['booking_date']),
            amountCents: AccountAmount::fromDecimal((string) $row['amount']),
            kind: AccountTransactionKind::tryFrom((string) $row['kind']) ?? AccountTransactionKind::Manual,
            description: $row['description'],
            counterparty: $row['counterparty'],
            receiptId: $row['receipt_id'],
            invoiceId: $row['invoice_id'],
            importHash: $row['import_hash'],
            createdAt: new DateTimeImmutable($row['created_at']),
            updatedAt: new DateTimeImmutable($row['updated_at']),
        );
    }
}
