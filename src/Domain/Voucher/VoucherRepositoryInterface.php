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
}
