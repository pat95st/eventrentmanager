<?php

declare(strict_types=1);

namespace App\Infrastructure\BankImport;

use App\Domain\BankImport\BankStatementParserInterface;
use App\Domain\BankImport\BankTransaction;
use App\Domain\BankImport\UnsupportedBankStatementException;
use DateTimeImmutable;

/**
 * Parst MT940-Kontoauszüge (SWIFT-Format). Buchungssätze stehen in
 * ":61:"-Zeilen, der zugehörige Verwendungszweck in der/den direkt danach
 * folgenden ":86:"-Zeile(n) (MT940 erlaubt hier mehrzeilige Fortsetzungen,
 * siehe Schleife unten). Zahlungspflichtigen-/Zahlungsempfängername wird
 * heuristisch aus den SEPA-Unterfeldern "?32"/"?33" extrahiert, die viele
 * Banken innerhalb der ":86:"-Zeile mit "?"-Tags strukturieren.
 */
final class Mt940Parser implements BankStatementParserInterface
{
    public function parse(string $content): array
    {
        // Zeilen können mit \r\n enden; zusätzlich kommt es vor, dass ein
        // Fortsetzungszeilenumbruch ohne führendes Tag einfach die
        // vorherige Zeile fortsetzt (hier bewusst NICHT zusammengeführt,
        // um die Buchungssatz-Erkennung einfach zu halten — der ganz
        // überwiegende Regelfall hat ein Tag pro Zeile).
        $lines = preg_split('/\r\n|\r|\n/', $content) ?: [];

        $transactions = [];
        $i = 0;
        $count = count($lines);

        while ($i < $count) {
            $line = $lines[$i];

            if (!str_starts_with($line, ':61:')) {
                $i++;

                continue;
            }

            $statementLine = substr($line, 4);
            $purposeLines = [];
            $j = $i + 1;
            while ($j < $count && str_starts_with($lines[$j], ':86:')) {
                $purposeLines[] = substr($lines[$j], 4);
                $j++;
            }

            $transaction = $this->parseStatementLine($statementLine, implode(' ', $purposeLines));
            if ($transaction !== null) {
                $transactions[] = $transaction;
            }

            $i = $j > $i + 1 ? $j : $i + 1;
        }

        if ($transactions === []) {
            throw new UnsupportedBankStatementException('Die MT940-Datei enthält keine auswertbaren Buchungen.');
        }

        return $transactions;
    }

    private function parseStatementLine(string $statementLine, string $purposeText): ?BankTransaction
    {
        // Format (vereinfacht): JJMMTT[MMTT]{2,1}CD...Betrag...
        // Beispiel: 260115C100,00NTRFNONREF
        // Gruppe 1: Wertstellungsdatum JJMMTT
        // Gruppe 2: optionale Buchungsdatum-Fortsetzung MMTT (wird ignoriert,
        //           Wertstellungsdatum ist für den Abgleich ausreichend)
        // Gruppe 3: Soll/Haben-Kennung: C(redit)/D(ebit)/RC/RD
        // Gruppe 4: Betrag mit Komma als Dezimaltrennzeichen
        $pattern = '/^(\d{6})(\d{4})?(R?[CD])(?:[A-Z])?([0-9,]+)/';
        if (!preg_match($pattern, $statementLine, $matches)) {
            return null;
        }

        $dateRaw = $matches[1];
        $creditDebitRaw = $matches[3];
        $amountRaw = $matches[4];

        try {
            $bookingDate = DateTimeImmutable::createFromFormat('ymd', $dateRaw) ?: null;
        } catch (\Throwable) {
            $bookingDate = null;
        }
        if ($bookingDate === null) {
            return null;
        }

        // "RC"/"RD" sind Stornos der jeweils entgegengesetzten Buchung —
        // fachlich bleibt die Richtung des Zeichens maßgeblich.
        $isCredit = str_ends_with($creditDebitRaw, 'C');

        $amount = number_format((float) str_replace(',', '.', $amountRaw), 2, '.', '');

        [$counterpartyName, $reference] = $this->extractSepaFields($purposeText);

        return new BankTransaction(
            bookingDate: $bookingDate,
            amount: $amount,
            currency: 'EUR',
            isCredit: $isCredit,
            purpose: trim($purposeText) !== '' ? trim($purposeText) : null,
            counterpartyName: $counterpartyName,
            reference: $reference,
        );
    }

    /**
     * Extrahiert Zahlungspflichtigen-/Zahlungsempfängername (SEPA-Unterfeld
     * "?32"/"?33", je nach Bank mal nur ?32 verwendet) sowie eine optionale
     * Referenz (Unterfeld "?20") aus der ":86:"-Zeile.
     *
     * @return array{0: string|null, 1: string|null}
     */
    private function extractSepaFields(string $purposeText): array
    {
        $name = null;
        if (preg_match('/\?3[23]([^?]*)/', $purposeText, $nameMatch)) {
            $name = trim($nameMatch[1]) !== '' ? trim($nameMatch[1]) : null;
        }

        $reference = null;
        if (preg_match('/\?20([^?]*)/', $purposeText, $refMatch)) {
            $reference = trim($refMatch[1]) !== '' ? trim($refMatch[1]) : null;
        }

        return [$name, $reference];
    }
}
