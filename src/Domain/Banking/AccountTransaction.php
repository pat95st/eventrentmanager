<?php

declare(strict_types=1);

namespace App\Domain\Banking;

use DateTimeImmutable;

/**
 * Eine Buchung auf einem Firmenkonto (Migration 131). $amountCents ist
 * vorzeichenbehaftet: positiv = Zahlungseingang, negativ = Zahlungsausgang.
 */
final class AccountTransaction
{
    public function __construct(
        public readonly string $id,
        public readonly string $bankAccountId,
        public DateTimeImmutable $bookingDate,
        public int $amountCents,
        public AccountTransactionKind $kind,
        public string $description,
        public ?string $counterparty,
        public ?string $receiptId,
        public ?string $invoiceId,
        public ?string $importHash,
        public readonly DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {
    }

    public function isIncome(): bool
    {
        return $this->amountCents > 0;
    }
}
