<?php

declare(strict_types=1);

namespace App\Domain\Voucher;

use DateTimeImmutable;

final class Voucher
{
    public function __construct(
        public readonly string $id,
        public readonly string $code,
        public VoucherDiscountType $discountType,
        // Je nach discountType ein Prozentsatz (0-100) oder ein Betrag in EUR;
        // als String geführt, um Rundungsfehler bei DECIMAL zu vermeiden
        // (analog zu Article::$purchasePrice, siehe App\Shared\Money).
        public string $discountValue,
        public DateTimeImmutable $validFrom,
        public DateTimeImmutable $validUntil,
        public ?string $notes,
        public readonly DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {
    }

    /** Vergleich taggenau (validFrom/validUntil sind DATE-Spalten, siehe Migration 074). */
    public function isExpired(DateTimeImmutable $now): bool
    {
        return $now->format('Y-m-d') > $this->validUntil->format('Y-m-d');
    }

    public function hasStarted(DateTimeImmutable $now): bool
    {
        return $now->format('Y-m-d') >= $this->validFrom->format('Y-m-d');
    }

    public function isValidNow(DateTimeImmutable $now): bool
    {
        return $this->hasStarted($now) && !$this->isExpired($now);
    }
}
