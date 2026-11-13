<?php

declare(strict_types=1);

namespace App\Domain\DocumentStorage;

interface StorageFolderRepositoryInterface
{
    public function save(StorageFolder $folder): void;

    public function findByOwner(StorageFolderOwnerType $ownerType, string $ownerId): ?StorageFolder;

    /** @return array<string, StorageFolder> Kunden-ID => Ordner, für alle Kunden mit eigenem Ordner. */
    public function customerFolders(): array;

    /**
     * Zieht die Pfade aller Ordner nach, die UNTERHALB von $oldPath liegen
     * (Veranstaltungen als Unterordner eines umbenannten Kundenordners).
     */
    public function replacePathPrefix(string $storageTargetId, string $oldPath, string $newPath): void;

    /** Entfernt die Verknüpfungen aller Ordner unterhalb von $path (der Ordner selbst ist nicht gemeint). */
    public function deleteBelowPath(string $storageTargetId, string $path): void;

    public function delete(string $id): void;
}
