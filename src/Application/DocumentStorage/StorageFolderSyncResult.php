<?php

declare(strict_types=1);

namespace App\Application\DocumentStorage;

use App\Domain\DocumentStorage\StorageFolder;

/** Ergebnis von SyncStorageFolderHandler, für die Rückmeldung an den Benutzer. */
final class StorageFolderSyncResult
{
    /**
     * @param string|null $newPassword Frisch erzeugtes Freigabe-Passwort — wird
     *        nirgends gespeichert und darf nur dieses eine Mal angezeigt werden.
     * @param list<string> $warnings Dinge, die nicht geklappt haben, ohne dass
     *        der Rest scheitert (z. B. Kunde ohne E-Mail-Adresse).
     */
    public function __construct(
        public readonly ?StorageFolder $folder,
        public readonly ?string $newPassword = null,
        public readonly array $warnings = [],
    ) {
    }
}
