<?php

declare(strict_types=1);

namespace App\Domain\Federation;

interface PartnerInstanceRepositoryInterface
{
    public function save(PartnerInstance $partner): void;

    public function findById(string $id): ?PartnerInstance;

    public function findByInboundTokenHash(string $hash): ?PartnerInstance;

    /** @return PartnerInstance[] ordered by name */
    public function findAll(): array;

    public function delete(string $id): void;
}
