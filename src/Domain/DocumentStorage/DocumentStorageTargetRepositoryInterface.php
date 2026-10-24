<?php

declare(strict_types=1);

namespace App\Domain\DocumentStorage;

interface DocumentStorageTargetRepositoryInterface
{
    public function save(DocumentStorageTarget $target): void;

    public function findById(string $id): ?DocumentStorageTarget;

    /** @return DocumentStorageTarget[] */
    public function findAll(): array;

    public function delete(string $id): void;
}
