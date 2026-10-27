<?php

declare(strict_types=1);

namespace App\Infrastructure\Csv;

/**
 * Feste Spaltenvorlage für den Kunden-CSV-Import (siehe
 * App\Application\Customer\Command\ImportCustomersFromCsvHandler) — bewusst
 * eine FESTE Vorlage statt einer frei zuordenbaren Spaltenzuordnung (siehe
 * Entscheidung in der Aufgabenstellung): der Admin lädt diese Vorlage
 * herunter, befüllt sie in Excel/Calc und lädt sie unverändert wieder hoch.
 *
 * Dieselbe Spaltendefinition wird an BEIDEN Enden verwendet — hier für den
 * Download (buildTemplate()) UND im Handler für die Kopfzeilen-Prüfung beim
 * Hochladen (HEADER) — damit beide niemals auseinanderlaufen können.
 */
final class CustomerCsvTemplate
{
    /** @var string[] */
    public const HEADER = ['Art', 'Firma', 'Vorname', 'Nachname', 'Straße', 'Adresszusatz', 'PLZ', 'Ort', 'Telefon', 'E-Mail'];

    public static function buildTemplate(): string
    {
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            throw new \RuntimeException('CSV-Ausgabestream konnte nicht geöffnet werden.');
        }

        // BOM, damit Excel unter Windows die UTF-8-Kodierung (Umlaute) korrekt erkennt.
        fwrite($handle, "\xEF\xBB\xBF");

        fputcsv($handle, self::HEADER, ';');
        // Eine Beispielzeile mit gültigen Werten, damit klar wird, was in
        // welche Spalte gehört (insbesondere "Art": privat/geschäftlich).
        fputcsv($handle, ['privat', '', 'Max', 'Mustermann', 'Musterstraße 1', '', '12345', 'Musterstadt', '0123 456789', 'max@example.com'], ';');
        fputcsv($handle, ['geschäftlich', 'Musterfirma GmbH', 'Erika', 'Musterfrau', 'Musterallee 2', '2. OG', '54321', 'Musterhausen', '', 'erika@example.com'], ';');

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv === false ? '' : $csv;
    }
}
