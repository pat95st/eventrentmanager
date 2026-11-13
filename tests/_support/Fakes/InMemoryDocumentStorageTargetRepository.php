<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Domain\DocumentStorage\DocumentStorageTarget;
use App\Domain\DocumentStorage\DocumentStorageTargetRepositoryInterface;

/** In-Memory-Test-Double für DocumentStorageTargetRepositoryInterface (siehe tests/README.md). */
final class InMemoryDocumentStorageTargetRepository implements DocumentStorageTargetRepositoryInterface
{
    /** @var array<string, DocumentStorageTarget> */
    private array $targets = [];

    public function save(DocumentStorageTarget $target): void
    {
        $this->targets[$target->id] = $target;
    }

    public function findById(string $id): ?DocumentStorageTarget
    {
        return $this->targets[$id] ?? null;
    }

    public function findAll(): array
    {
        return array_values($this->targets);
    }

    public function delete(string $id): void
    {
        unset($this->targets[$id]);
    }
}
