<?php

declare(strict_types=1);

namespace App\Infrastructure\Datev;

use App\Domain\Datev\DatevBooking;
use App\Domain\Datev\DatevSettings;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Schreibt einen DATEV-Buchungsstapel im "DATEV-Format" (Kennung EXTF,
 * Header-Version 700, Datenkategorie 21, Formatversion 13).
 *
 * Aufbau der Datei:
 *  - Zeile 1: Header (Berater-/Mandantennummer, Wirtschaftsjahr, Zeitraum, …)
 *  - Zeile 2: Spaltenüberschriften (125 Felder)
 *  - ab Zeile 3: je Buchung ein Datensatz
 *
 * Formatregeln: Trennzeichen ";", Textfelder in Anführungszeichen
 * (innere Anführungszeichen verdoppelt), Dezimalkomma ohne
 * Tausendertrennzeichen, Zeilenende CRLF, Zeichensatz Windows-1252 (ANSI).
 * Ein Buchungsstapel darf das Wirtschaftsjahr nicht überschreiten — das
 * stellt der Aufrufer sicher (siehe DatevExportBuilder::splitByFiscalYear()).
 */
final class DatevBuchungsstapelWriter
{
    /** Spaltenüberschriften Buchungsstapel, Formatversion 13. */
    public const COLUMNS = [
        'Umsatz (ohne Soll/Haben-Kz)', 'Soll/Haben-Kennzeichen', 'WKZ Umsatz', 'Kurs', 'Basis-Umsatz', 'WKZ Basis-Umsatz',
        'Konto', 'Gegenkonto (ohne BU-Schlüssel)', 'BU-Schlüssel', 'Belegdatum', 'Belegfeld 1', 'Belegfeld 2', 'Skonto',
        'Buchungstext', 'Postensperre', 'Diverse Adressnummer', 'Geschäftspartnerbank', 'Sachverhalt', 'Zinssperre', 'Beleglink',
        'Beleginfo - Art 1', 'Beleginfo - Inhalt 1', 'Beleginfo - Art 2', 'Beleginfo - Inhalt 2',
        'Beleginfo - Art 3', 'Beleginfo - Inhalt 3', 'Beleginfo - Art 4', 'Beleginfo - Inhalt 4',
        'Beleginfo - Art 5', 'Beleginfo - Inhalt 5', 'Beleginfo - Art 6', 'Beleginfo - Inhalt 6',
        'Beleginfo - Art 7', 'Beleginfo - Inhalt 7', 'Beleginfo - Art 8', 'Beleginfo - Inhalt 8',
        'KOST1 - Kostenstelle', 'KOST2 - Kostenstelle', 'Kost-Menge', 'EU-Land u. UStID (Bestimmung)', 'EU-Steuersatz (Bestimmung)',
        'Abw. Versteuerungsart', 'Sachverhalt L+L', 'Funktionsergänzung L+L', 'BU 49 Hauptfunktionstyp', 'BU 49 Hauptfunktionsnummer',
        'BU 49 Funktionsergänzung',
        'Zusatzinformation - Art 1', 'Zusatzinformation- Inhalt 1', 'Zusatzinformation - Art 2', 'Zusatzinformation- Inhalt 2',
        'Zusatzinformation - Art 3', 'Zusatzinformation- Inhalt 3', 'Zusatzinformation - Art 4', 'Zusatzinformation- Inhalt 4',
        'Zusatzinformation - Art 5', 'Zusatzinformation- Inhalt 5', 'Zusatzinformation - Art 6', 'Zusatzinformation- Inhalt 6',
        'Zusatzinformation - Art 7', 'Zusatzinformation- Inhalt 7', 'Zusatzinformation - Art 8', 'Zusatzinformation- Inhalt 8',
        'Zusatzinformation - Art 9', 'Zusatzinformation- Inhalt 9', 'Zusatzinformation - Art 10', 'Zusatzinformation- Inhalt 10',
        'Zusatzinformation - Art 11', 'Zusatzinformation- Inhalt 11', 'Zusatzinformation - Art 12', 'Zusatzinformation- Inhalt 12',
        'Zusatzinformation - Art 13', 'Zusatzinformation- Inhalt 13', 'Zusatzinformation - Art 14', 'Zusatzinformation- Inhalt 14',
        'Zusatzinformation - Art 15', 'Zusatzinformation- Inhalt 15', 'Zusatzinformation - Art 16', 'Zusatzinformation- Inhalt 16',
        'Zusatzinformation - Art 17', 'Zusatzinformation- Inhalt 17', 'Zusatzinformation - Art 18', 'Zusatzinformation- Inhalt 18',
        'Zusatzinformation - Art 19', 'Zusatzinformation- Inhalt 19', 'Zusatzinformation - Art 20', 'Zusatzinformation- Inhalt 20',
        'Stück', 'Gewicht', 'Zahlweise', 'Forderungsart', 'Veranlagungsjahr', 'Zugeordnete Fälligkeit', 'Skontotyp', 'Auftragsnummer',
        'Buchungstyp', 'USt-Schlüssel (Anzahlungen)', 'EU-Land (Anzahlungen)', 'Sachverhalt L+L (Anzahlungen)',
        'EU-Steuersatz (Anzahlungen)', 'Erlöskonto (Anzahlungen)', 'Herkunft-Kz', 'Buchungs GUID', 'KOST-Datum',
        'SEPA-Mandatsreferenz', 'Skontosperre', 'Gesellschaftername', 'Beteiligtennummer', 'Identifikationsnummer',
        'Zeichnernummer', 'Postensperre bis', 'Bezeichnung SoBil-Sachverhalt', 'Kennzeichen SoBil-Buchung', 'Festschreibung',
        'Leistungsdatum', 'Datum Zuord. Steuerperiode', 'Fälligkeit', 'Generalumkehr (GU)', 'Steuersatz', 'Land',
        'Abrechnungsreferenz', 'BVV-Position', 'EU-Land u. UStID (Ursprung)', 'EU-Steuersatz (Ursprung)', 'Abw. Skontokonto',
    ];

    private const COL_BELEGINFO_START = 20; // 0-basiert: "Beleginfo - Art 1"
    private const COL_LEISTUNGSDATUM = 114;

    /**
     * @param DatevBooking[] $bookings
     * @return string Dateiinhalt in Windows-1252
     */
    public function write(
        DatevSettings $settings,
        DateTimeImmutable $fiscalYearStart,
        DateTimeImmutable $periodFrom,
        DateTimeImmutable $periodTo,
        string $label,
        array $bookings,
        ?DateTimeImmutable $createdAt = null,
    ): string {
        if ($settings->consultantNumber === null || $settings->clientNumber === null) {
            throw new InvalidArgumentException('Für den DATEV-Export werden Berater- und Mandantennummer benötigt.');
        }
        if ($periodFrom < $fiscalYearStart || $periodTo >= $fiscalYearStart->modify('+1 year')) {
            throw new InvalidArgumentException('Ein DATEV-Buchungsstapel darf das Wirtschaftsjahr nicht überschreiten.');
        }

        $createdAt ??= new DateTimeImmutable();

        $header = [
            $this->text('EXTF'),
            '700',
            '21',
            $this->text('Buchungsstapel'),
            '13',
            $createdAt->format('YmdHis') . substr($createdAt->format('u'), 0, 3),
            '',
            $this->text('RE'),
            $this->text('Materialverleih'),
            $this->text(''),
            (string) $settings->consultantNumber,
            (string) $settings->clientNumber,
            $fiscalYearStart->format('Ymd'),
            (string) $settings->accountLength,
            $periodFrom->format('Ymd'),
            $periodTo->format('Ymd'),
            $this->text(mb_substr($label, 0, 30)),
            $this->text(''),
            '1', // Buchungstyp: Finanzbuchführung
            '0', // Rechnungslegungszweck: unabhängig
            '0', // Festschreibung: nein — der Steuerberater kann noch korrigieren
            $this->text('EUR'),
            '',
            $this->text(''),
            '',
            '',
            $this->text($settings->chartOfAccounts->value),
            '',
            '',
            $this->text(''),
            $this->text(''),
        ];

        $lines = [
            implode(';', $header),
            implode(';', array_map($this->text(...), self::COLUMNS)),
        ];

        foreach ($bookings as $booking) {
            $lines[] = implode(';', $this->row($booking));
        }

        $content = implode("\r\n", $lines) . "\r\n";

        return mb_convert_encoding($content, 'Windows-1252', 'UTF-8');
    }

    /** @return string[] */
    private function row(DatevBooking $booking): array
    {
        $fields = array_fill(0, count(self::COLUMNS), '');

        $fields[0] = number_format($booking->amount, 2, ',', '');
        $fields[1] = $this->text($booking->isDebit ? 'S' : 'H');
        $fields[2] = $this->text('EUR');
        $fields[6] = (string) $booking->account;
        $fields[7] = (string) $booking->contraAccount;
        $fields[8] = $booking->taxKey !== null && $booking->taxKey !== '' ? $this->text($booking->taxKey) : '';
        $fields[9] = $booking->documentDate->format('dm');
        $fields[10] = $this->text(self::documentField($booking->documentField1));
        $fields[11] = $booking->documentField2 !== null ? $this->text(self::documentField($booking->documentField2, 12)) : '';
        $fields[13] = $this->text(mb_substr($this->singleLine($booking->text), 0, 60));

        $index = self::COL_BELEGINFO_START;
        foreach (array_slice($booking->documentInfo, 0, 8, true) as $kind => $content) {
            $fields[$index] = $this->text(mb_substr($this->singleLine((string) $kind), 0, 20));
            $fields[$index + 1] = $this->text(mb_substr($this->singleLine($content), 0, 210));
            $index += 2;
        }

        if ($booking->serviceDate !== null) {
            $fields[self::COL_LEISTUNGSDATUM] = $booking->serviceDate->format('dmY');
        }

        return $fields;
    }

    /**
     * Belegfeld 1 erlaubt nur Buchstaben, Ziffern und $ & % * + - / und ist
     * auf 36 Zeichen begrenzt (Belegfeld 2: 12 Zeichen). Andere Zeichen
     * werden durch "-" ersetzt.
     */
    public static function documentField(string $value, int $maxLength = 36): string
    {
        $value = preg_replace('/[^A-Za-z0-9$&%*+\-\/]+/', '-', $value) ?? '';
        $value = trim($value, '-');

        return substr($value, 0, $maxLength);
    }

    private function singleLine(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    }

    private function text(string $value): string
    {
        return '"' . str_replace('"', '""', $value) . '"';
    }
}
