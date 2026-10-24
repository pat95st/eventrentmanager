<?php

declare(strict_types=1);

namespace App\Domain\Voucher;

interface VoucherRedemptionRepositoryInterface
{
    public function save(VoucherRedemption $redemption): void;

    public function findByVoucherId(string $voucherId): ?VoucherRedemption;

    public function findByRentalId(string $rentalId): ?VoucherRedemption;

    /**
     * @param string[] $voucherIds
     * @return array<string, VoucherRedemption> indiziert nach voucherId (für die Listenansicht, siehe VoucherController::index())
     */
    public function findByVoucherIds(array $voucherIds): array;

    /** "Einlösung aufheben": löscht die Zuordnung wieder, damit der Gutschein erneut eingelöst werden kann. */
    public function deleteByVoucherId(string $voucherId): void;
}
