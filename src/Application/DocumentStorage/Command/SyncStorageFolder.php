<?php

declare(strict_types=1);

namespace App\Application\DocumentStorage\Command;

use App\Application\Command\CommandInterface;
use App\Domain\DocumentStorage\StorageFolderOwnerType;

/**
 * Gleicht den Ordner einer Veranstaltung bzw. eines Kunden im externen
 * Dateiablagesystem mit den Angaben aus dem Formular ab (Migration 148):
 * anlegen, Freigabe ein-/ausschalten, Berechtigung ändern, Passwort neu
 * erzeugen. Wird vom Controller NACH dem erfolgreichen Speichern der
 * Veranstaltung/des Kunden abgeschickt.
 */
final class SyncStorageFolder implements CommandInterface
{
    public function __construct(
        public readonly StorageFolderOwnerType $ownerType,
        public readonly string $ownerId,
        public readonly bool $enabled,
        public readonly string $storageTargetId = '',
        // Nur Veranstaltungen: als Unterordner im Ordner des Kunden anlegen.
        public readonly bool $inCustomerFolder = false,
        public readonly bool $shareEnabled = false,
        public readonly string $sharePermission = 'read',
        public readonly bool $regeneratePassword = false,
        public readonly bool $resendMail = false,
    ) {
    }
}
