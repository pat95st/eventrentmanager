<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Domain\DocumentStorage\StorageFolder;
use App\Domain\DocumentStorage\StorageFolderOwnerType;
use App\Domain\DocumentStorage\StorageFolderRepositoryInterface;

/** In-Memory-Test-Double für StorageFolderRepositoryInterface (siehe tests/README.md). */
final class InMemoryStorageFolderRepository implements StorageFolderRepositoryInterface
{
    /** @var array<string, StorageFolder> */
    private array $folders = [];

    public function save(StorageFolder $folder): void
    {
        $this->folders[$folder->id] = $folder;
    }

    public function findByOwner(StorageFolderOwnerType $ownerType, string $ownerId): ?StorageFolder
    {
        foreach ($this->folders as $folder) {
            if ($folder->ownerType === $ownerType && $folder->ownerId === $ownerId) {
                return $folder;
            }
        }

        return null;
    }

    public function customerFolderTargets(): array
    {
        $result = [];
        foreach ($this->folders as $folder) {
            if ($folder->ownerType === StorageFolderOwnerType::Customer) {
                $result[$folder->ownerId] = $folder->storageTargetId;
            }
        }

        return $result;
    }

    public function delete(string $id): void
    {
        unset($this->folders[$id]);
    }
}
