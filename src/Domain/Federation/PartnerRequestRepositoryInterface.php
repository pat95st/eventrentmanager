<?php

declare(strict_types=1);

namespace App\Domain\Federation;

interface PartnerRequestRepositoryInterface
{
    public function save(PartnerRequest $request): void;

    public function findById(string $id): ?PartnerRequest;

    public function findByReference(string $partnerId, PartnerRequestDirection $direction, string $reference): ?PartnerRequest;

    /** @return PartnerRequest[] newest first */
    public function findRecent(int $limit): array;

    /** @return PartnerRequest[] all requests linked to a rental (both directions), newest first */
    public function findByRental(string $rentalId): array;

    public function countPendingIncoming(): int;
}
