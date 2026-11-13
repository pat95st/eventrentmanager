<?php

declare(strict_types=1);

namespace App\Domain\DocumentStorage;

use DateTimeImmutable;

/**
 * Ein für eine Veranstaltung oder einen Kunden angelegter Ordner auf einem
 * Ziellaufwerk (Migration 148), optional mit Freigabe-Link für den Kunden
 * (nur Nextcloud/ownCloud). Der Pfad wird beim Anlegen festgeschrieben.
 */
final class StorageFolder
{
    public function __construct(
        public readonly string $id,
        public readonly StorageFolderOwnerType $ownerType,
        public readonly string $ownerId,
        public readonly string $storageTargetId,
        public readonly string $path,
        public readonly bool $inCustomerFolder,
        public ?string $shareId,
        public ?string $shareUrl,
        public ?SharePermission $sharePermission,
        public bool $shareHasPassword,
        public ?DateTimeImmutable $shareMailedAt,
        public ?string $shareMailedTo,
        public readonly DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {
    }

    public function isShared(): bool
    {
        return $this->shareId !== null;
    }

    /** Letzte Ebene des Pfads, z. B. für Betreff/Text der Freigabe-Mail. */
    public function name(): string
    {
        $segments = explode('/', trim($this->path, '/'));

        return (string) end($segments);
    }

    public function clearShare(): void
    {
        $this->shareId = null;
        $this->shareUrl = null;
        $this->sharePermission = null;
        $this->shareHasPassword = false;
        $this->shareMailedAt = null;
        $this->shareMailedTo = null;
    }
}
