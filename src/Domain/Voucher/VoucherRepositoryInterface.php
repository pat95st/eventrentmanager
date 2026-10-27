<?php

declare(strict_types=1);

namespace App\Domain\Voucher;

interface VoucherRepositoryInterface
{
    public function save(Voucher $voucher): void;

    public function findById(string $id): ?Voucher;

    public function findByCode(string $code): ?Voucher;

    /** @return Voucher[] sortiert nach created_at absteigend (neueste zuerst) */
    public function findAll(?string $search = null, ?int $limit = null, ?int $offset = null): array;

    /** Gesamtanzahl der Treffer für dieselben Filter wie findAll(), ohne LIMIT/OFFSET (für die Paginierung). */
    public function countAll(?string $search = null): int;

    public function delete(string $id): void;

    /**
     * Ersetzt die komplette Kunden-Einschränkung eines Gutscheins (siehe
     * Migration 099) — ein leeres Array hebt die Einschränkung vollständig
     * auf (= für alle Kunden gültig).
     *
     * @param string[] $customerIds
     */
    public function replaceCustomerRestriction(string $voucherId, array $customerIds): void;

    /** @return string[] Leeres Array = keine Einschränkung (für alle Kunden gültig). */
    public function findCustomerIds(string $voucherId): array;

    /**
     * @param string[] $voucherIds
     * @return array<string, string[]> voucherId => customerIds, NUR für Gutscheine MIT Einschränkung (unbeschränkte tauchen hier nicht auf)
     */
    public function findCustomerIdsForVouchers(array $voucherIds): array;
}
