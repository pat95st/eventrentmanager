<?php

declare(strict_types=1);

namespace App\Application\Customer\Command;

use App\Domain\Customer\Customer;
use App\Domain\Customer\CustomerRepositoryInterface;
use App\Domain\Customer\CustomerType;
use App\Domain\Exception\ValidationException;
use App\Domain\Numbering\NumberedDocumentType;
use App\Infrastructure\Csv\CustomerCsvTemplate;
use App\Infrastructure\Persistence\Pdo\DocumentNumberSequence;
use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Shared\Uuid;
use DateTimeImmutable;
use PDO;
use Throwable;

/**
 * Importiert mehrere Kunden auf einmal aus einer CSV-Datei, die exakt der
 * festen Vorlage entspricht (siehe CustomerCsvTemplate, Entscheidung in der
 * Aufgabenstellung: feste Vorlage statt frei zuordenbarer Spalten).
 *
 * "Alles oder nichts": ALLE Zeilen werden zuerst mit derselben Validierung
 * wie beim einzelnen Anlegen (ValidatesCustomerInput, siehe
 * CreateCustomerHandler) geprüft, bevor überhaupt ein Kunde gespeichert
 * wird. Erst wenn JEDE Zeile fehlerfrei ist, werden alle Kunden in einer
 * gemeinsamen Transaktion angelegt — ein teilweise fehlgeschlagener Import
 * (z. B. "47 von 50 Zeilen importiert") wäre für den Admin schwer
 * nachzuvollziehen und schwer zu korrigieren (welche 3 fehlten?). Legt
 * IMMER neue Kunden an, erkennt also keine Dubletten gegen bereits
 * vorhandene Kunden — das entspricht dem in der Aufgabenstellung
 * beschriebenen Anwendungsfall (Übernahme eines bestehenden Kundenstamms).
 */
final class ImportCustomersFromCsvHandler implements CommandHandlerInterface
{
    use ValidatesCustomerInput;

    private const MAX_SIZE_BYTES = 2 * 1024 * 1024; // 2 MB
    // Rein defensiv gegen Fehlbedienung (z. B. versehentlich eine riesige
    // Fremd-Exportdatei hochgeladen) — kein fachlicher Grund für genau 500.
    private const MAX_ROWS = 500;

    public function __construct(
        private readonly CustomerRepositoryInterface $customers,
        private readonly DocumentNumberSequence $numberSequence,
        private readonly PDO $pdo,
    ) {
    }

    /** @return int Anzahl der importierten Kunden. */
    public function handle(CommandInterface $command): int
    {
        assert($command instanceof ImportCustomersFromCsv);

        $file = $command->uploadedFile;
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE || ($file['tmp_name'] ?? '') === '') {
            throw new ValidationException(['file' => 'Bitte eine CSV-Datei auswählen.']);
        }

        if ($file['size'] > self::MAX_SIZE_BYTES) {
            throw new ValidationException(['file' => 'Die Datei darf höchstens 2 MB groß sein.']);
        }

        $rows = $this->parseCsv($file['tmp_name']);

        if ($rows === []) {
            throw new ValidationException(['file' => 'Die Datei enthält keine Datenzeilen.']);
        }

        if (count($rows) > self::MAX_ROWS) {
            throw new ValidationException(['file' => 'Es können höchstens ' . self::MAX_ROWS . ' Zeilen auf einmal importiert werden.']);
        }

        $errors = [];
        $validatedRows = [];
        foreach ($rows as $rowNumber => $row) {
            [$rowErrors, $data] = $this->validateCustomerInput(
                $this->normalizeType($row['Art']),
                $row['Firma'],
                $row['Vorname'],
                $row['Nachname'],
                $row['Straße'],
                $row['Adresszusatz'],
                $row['PLZ'],
                $row['Ort'],
                $row['Telefon'],
                $row['E-Mail'],
            );

            if ($rowErrors !== []) {
                // Alle Feldfehler EINER Zeile zu einer Meldung zusammengefasst
                // (statt separater Einträge je Feld) — bei einem Import mit
                // vielen Zeilen bleibt die Fehlerliste so übersichtlich
                // (eine Zeile pro betroffener CSV-Zeile).
                $errors["Zeile {$rowNumber}"] = implode(' ', $rowErrors);

                continue;
            }

            $validatedRows[] = $data;
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $now = new DateTimeImmutable();

        // Siehe CreateCustomerHandler für die identische Begründung
        // (Nummernvergabe + Speichern in derselben Transaktion) — hier über
        // ALLE Zeilen hinweg EINE Transaktion, damit ein Fehler mitten im
        // Import (z. B. unerwarteter DB-Fehler) wirklich nichts übrig lässt.
        $this->pdo->beginTransaction();

        try {
            foreach ($validatedRows as $data) {
                $customerNumber = $this->numberSequence->allocateNext(NumberedDocumentType::Customer);

                $this->customers->save(new Customer(
                    id: Uuid::v4(),
                    customerNumber: $customerNumber,
                    type: $data['type'],
                    companyName: $data['companyName'],
                    firstName: $data['firstName'],
                    lastName: $data['lastName'],
                    street: $data['street'],
                    addressLine2: $data['addressLine2'],
                    postalCode: $data['postalCode'],
                    city: $data['city'],
                    phone: $data['phone'],
                    email: $data['email'],
                    telegramChatId: null,
                    createdAt: $now,
                    updatedAt: $now,
                ));
            }

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();

            throw $e;
        }

        return count($validatedRows);
    }

    /**
     * @return array<int, array<string, string>> 1-basiert nach Zeilennummer
     *     in der Datei (Kopfzeile = Zeile 1), damit Fehlermeldungen exakt
     *     auf die Zeile verweisen, die der Admin in seinem Tabellenprogramm
     *     sieht.
     */
    private function parseCsv(string $tmpName): array
    {
        $handle = fopen($tmpName, 'r');
        if ($handle === false) {
            throw new ValidationException(['file' => 'Die Datei konnte nicht gelesen werden.']);
        }

        // UTF-8-BOM überspringen, falls vorhanden (siehe CustomerCsvTemplate
        // — Excel unter Windows schreibt ihn beim Speichern i. d. R. mit).
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $header = fgetcsv($handle, 0, ';');
        if ($header === false) {
            fclose($handle);

            throw new ValidationException(['file' => 'Die Datei enthält keine Kopfzeile.']);
        }

        $normalizedHeader = array_map(static fn ($value) => trim((string) $value), $header);
        if ($normalizedHeader !== CustomerCsvTemplate::HEADER) {
            fclose($handle);

            throw new ValidationException(['file' => 'Das Spaltenformat entspricht nicht der Vorlage. Bitte die Vorlage herunterladen, befüllen und unverändert wieder hochladen.']);
        }

        $rows = [];
        $rowNumber = 1; // Kopfzeile
        while (($line = fgetcsv($handle, 0, ';')) !== false) {
            $rowNumber++;

            // Komplett leere Zeilen (z. B. eine abschließende Leerzeile beim
            // Speichern in Excel) werden stillschweigend übersprungen, statt
            // als Fehlerzeile mit "Bitte ... angeben"-Meldungen in jedem
            // Feld aufzuschlagen.
            if ($line === [null] || $line === ['']) {
                continue;
            }

            $padded = array_pad($line, count(CustomerCsvTemplate::HEADER), '');
            $rows[$rowNumber] = array_combine(CustomerCsvTemplate::HEADER, array_map(
                static fn ($value) => $value !== null ? (string) $value : '',
                array_slice($padded, 0, count(CustomerCsvTemplate::HEADER)),
            ));
        }

        fclose($handle);

        return $rows;
    }

    /** Übersetzt die deutsche Spaltenbeschriftung ("privat"/"geschäftlich") in den von CustomerType::tryFrom() erwarteten Enum-Wert. */
    private function normalizeType(string $rawType): string
    {
        $normalized = mb_strtolower(trim($rawType));

        return match ($normalized) {
            'geschäftlich', 'geschaeftlich', 'firma' => CustomerType::Geschaeftlich->value,
            'privat' => CustomerType::Privat->value,
            default => $normalized,
        };
    }
}
