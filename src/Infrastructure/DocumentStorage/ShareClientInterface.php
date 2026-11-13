<?php

declare(strict_types=1);

namespace App\Infrastructure\DocumentStorage;

use App\Domain\DocumentStorage\SharePermission;

/** Freigabe-Links für Ordner auf einem Ziellaufwerk (Nextcloud/ownCloud, siehe OcsShareClient). */
interface ShareClientInterface
{
    /**
     * @param string $remotePath Ordnerpfad relativ zum Basis-Pfad des Ziellaufwerks.
     * @throws StorageUploadException
     */
    public function createPublicLink(string $remotePath, SharePermission $permission, ?string $password, string $label): PublicShare;

    /** @throws StorageUploadException */
    public function updatePermission(string $shareId, SharePermission $permission): void;

    /** @throws StorageUploadException */
    public function updatePassword(string $shareId, string $password): void;

    /** Eine bereits entfernte Freigabe ist kein Fehler. @throws StorageUploadException */
    public function deleteShare(string $shareId): void;
}
