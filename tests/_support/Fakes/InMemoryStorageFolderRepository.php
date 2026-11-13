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

    public function customerFolders(): array
    {
        $result = [];
        foreach ($this->folders as $folder) {
            if ($folder->ownerType === StorageFolderOwnerType::Customer) {
                $result[$folder->ownerId] = $folder;
            }
        }

        return $result;
    }

    public function replacePathPrefix(string $storageTargetId, string $oldPath, string $newPath): void
    {
        foreach ($this->folders as $folder) {
            if ($folder->storageTargetId === $storageTargetId && str_starts_with($folder->path, $oldPath . '/')) {
                $folder->path = $newPath . substr($folder->path, strlen($oldPath));
            }
        }
    }

    public function deleteBelowPath(string $storageTargetId, string $path): void
    {
        foreach ($this->folders as $id => $folder) {
            if ($folder->storageTargetId === $storageTargetId && str_starts_with($folder->path, $path . '/')) {
                unset($this->folders[$id]);
            }
        }
    }

    public function delete(string $id): void
    {
        unset($this->folders[$id]);
    }
}
