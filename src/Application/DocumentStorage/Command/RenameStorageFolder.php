<?php

declare(strict_types=1);

namespace App\Application\DocumentStorage\Command;

use App\Application\Command\CommandInterface;
use App\Domain\DocumentStorage\StorageFolderOwnerType;

/**
 * Benennt den Ordner einer Veranstaltung bzw. eines Kunden so um, dass er
 * wieder zur Namensregel passt — nur nach ausdrücklicher Zustimmung des
 * Benutzers (Rückfrage nach dem Umbenennen der Veranstaltung/des Kunden).
 */
final class RenameStorageFolder implements CommandInterface
{
    public function __construct(
        public readonly StorageFolderOwnerType $ownerType,
        public readonly string $ownerId,
    ) {
    }
}
