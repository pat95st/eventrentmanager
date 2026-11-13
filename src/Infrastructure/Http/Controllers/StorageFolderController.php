<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controllers;

use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\StorageFolderForm;

/**
 * Antworten auf die Rückfragen zu Ordnern im Dateiablagesystem (Migration
 * 148), die layout.php nach dem Speichern einer Veranstaltung/eines Kunden
 * stellt. Worum es geht (welcher Ordner, welches Passwort), steht dabei
 * ausschließlich in der Session des Benutzers — die Anfrage selbst kann
 * weder einen anderen Ordner noch ein anderes Passwort benennen.
 */
final class StorageFolderController
{
    /** "Passwort an den Kunden senden" — auf dem vom Benutzer gewählten Weg. */
    public function sendPassword(Request $request): void
    {
        Response::redirect(StorageFolderForm::sendPendingPassword($request->input('delivery', '') ?? ''));
    }

    /** "Ordner ebenfalls umbenennen" nach dem Umbenennen einer Veranstaltung/eines Kunden. */
    public function rename(Request $request): void
    {
        Response::redirect(StorageFolderForm::renamePending());
    }
}
