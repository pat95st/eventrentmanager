<?php

declare(strict_types=1);

namespace App\Domain\Banking;

use DateTimeImmutable;

/** Kontoführung (Migration 131): Anfangsbestand und Buchungen je Bankverbindung. */
interface AccountLedgerRepositoryInterface
{
    /** @return array{cents: int, date: ?DateTimeImmutable} */
    public function openingBalance(string $bankAccountId): array;

    public function saveOpeningBalance(string $bankAccountId, int $cents, ?DateTimeImmutable $date): void;

    /**
     * Buchungen im Zeitraum (beide Grenzen inklusive), nach Datum und
     * Erfassung aufsteigend sortiert.
     *
     * @return AccountTransaction[]
     */
    public function findBetween(string $bankAccountId, DateTimeImmutable $from, DateTimeImmutable $to): array;

    /** Summe aller Buchungen ab $from (inklusive, null = ohne Untergrenze) bis vor $before (exklusive), in Cent. */
    public function sumBetween(string $bankAccountId, ?DateTimeImmutable $from, DateTimeImmutable $before): int;

    public function findById(string $id): ?AccountTransaction;

    /** @return AccountTransaction[] */
    public function findByReceipt(string $receiptId): array;

    /** @return AccountTransaction[] */
    public function findByInvoice(string $invoiceId): array;

    public function importHashExists(string $bankAccountId, string $importHash): bool;

    public function save(AccountTransaction $transaction): void;

    public function delete(string $id): void;
}
