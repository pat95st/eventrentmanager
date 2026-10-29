<?php

declare(strict_types=1);

namespace App\Domain\Settings;

interface BankAccountRepositoryInterface
{
    /** @return BankAccount[] Standardkonto zuerst, danach alphabetisch nach Bezeichnung */
    public function findAll(): array;

    public function findById(string $id): ?BankAccount;

    public function findDefault(): ?BankAccount;

    public function save(BankAccount $account): void;

    /** Setzt $id als einziges Standardkonto (alle übrigen verlieren die Markierung). */
    public function markAsDefault(string $id): void;

    public function delete(string $id): void;
}
