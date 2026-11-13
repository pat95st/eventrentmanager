<?php

declare(strict_types=1);

namespace App\Domain\DocumentStorage;

interface StorageFolderRepositoryInterface
{
    public function save(StorageFolder $folder): void;

    public function findByOwner(StorageFolderOwnerType $ownerType, string $ownerId): ?StorageFolder;

    /** @return array<string, string> Kunden-ID => ID des Ziellaufwerks, für alle Kunden mit eigenem Ordner. */
    public function customerFolderTargets(): array;

    public function delete(string $id): void;
}
