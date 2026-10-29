<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Settings\BankAccount;
use App\Domain\Settings\BankAccountRepositoryInterface;
use DateTimeImmutable;
use PDO;

final class MariadbBankAccountRepository implements BankAccountRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findAll(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM bank_accounts ORDER BY is_default DESC, label ASC');

        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function findById(string $id): ?BankAccount
    {
        $stmt = $this->pdo->prepare('SELECT * FROM bank_accounts WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function findDefault(): ?BankAccount
    {
        $stmt = $this->pdo->query('SELECT * FROM bank_accounts ORDER BY is_default DESC, created_at ASC LIMIT 1');
        $row = $stmt->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function save(BankAccount $account): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO bank_accounts (id, label, account_holder, bank_name, iban, bic, is_default, created_at, updated_at)
             VALUES (:id, :label, :account_holder, :bank_name, :iban, :bic, :is_default, :created_at, :updated_at)
             ON DUPLICATE KEY UPDATE label = VALUES(label), account_holder = VALUES(account_holder), bank_name = VALUES(bank_name),
                 iban = VALUES(iban), bic = VALUES(bic), is_default = VALUES(is_default), updated_at = VALUES(updated_at)'
        );
        $stmt->execute([
            'id' => $account->id,
            'label' => $account->label,
            'account_holder' => $account->accountHolder,
            'bank_name' => $account->bankName,
            'iban' => $account->iban,
            'bic' => $account->bic,
            'is_default' => $account->isDefault ? 1 : 0,
            'created_at' => $account->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $account->updatedAt->format('Y-m-d H:i:s'),
        ]);
    }

    public function markAsDefault(string $id): void
    {
        $stmt = $this->pdo->prepare('UPDATE bank_accounts SET is_default = (id = :id)');
        $stmt->execute(['id' => $id]);
    }

    public function delete(string $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM bank_accounts WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): BankAccount
    {
        return new BankAccount(
            id: $row['id'],
            label: $row['label'],
            accountHolder: $row['account_holder'],
            bankName: $row['bank_name'],
            iban: $row['iban'],
            bic: $row['bic'],
            isDefault: (bool) $row['is_default'],
            createdAt: new DateTimeImmutable($row['created_at']),
            updatedAt: new DateTimeImmutable($row['updated_at']),
        );
    }
}
