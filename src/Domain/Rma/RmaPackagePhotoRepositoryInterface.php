<?php

declare(strict_types=1);

namespace App\Domain\Rma;

interface RmaPackagePhotoRepositoryInterface
{
    public function add(RmaPackagePhoto $photo): void;

    public function findById(string $id): ?RmaPackagePhoto;

    /** @return RmaPackagePhoto[] */
    public function findByRma(string $rmaId): array;

    public function delete(string $id): void;
}
