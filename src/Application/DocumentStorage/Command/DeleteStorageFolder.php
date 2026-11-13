<?php

declare(strict_types=1);

namespace App\Application\DocumentStorage\Command;

use App\Application\Command\CommandInterface;
use App\Domain\DocumentStorage\StorageFolder;

/**
 * Löscht einen Ordner samt Inhalt auf dem Speicherziel — nur nach
 * ausdrücklicher Zustimmung des Benutzers beim Löschen der Veranstaltung
 * bzw. des Kunden.
 *
 * Übergeben wird der VOR dem Löschen des Datensatzes geladene Ordner: die
 * Verknüpfung verschwindet zusammen mit der Veranstaltung/dem Kunden
 * (ON DELETE CASCADE), der Ordner wird aber erst gelöscht, NACHDEM das
 * Löschen des Datensatzes tatsächlich geklappt hat.
 */
final class DeleteStorageFolder implements CommandInterface
{
    public function __construct(public readonly StorageFolder $folder)
    {
    }
}
