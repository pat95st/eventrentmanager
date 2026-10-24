<?php

declare(strict_types=1);

namespace App\Domain\BankImport;

/**
 * Teilstring-Matching-Heuristik, um eine Rechnungsnummer im Verwendungszweck
 * einer Gutschrift wiederzufinden — robust gegen von Banken veränderte/
 * entfernte Trennzeichen (z. B. "RE-2026-001" vs. "RE2026001" vs.
 * "RE 2026 001"), ohne eine an das aktuell konfigurierte Nummernschema
 * gebundene Regex zu benötigen (siehe Designentscheidung in der Projekt-
 * Dokumentation).
 */
final class InvoiceNumberMatcher
{
    /**
     * Normalisiert einen String für den Abgleich: entfernt alles außer
     * Buchstaben/Ziffern, wandelt in Großbuchstaben um.
     */
    public static function normalize(string $value): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $value) ?? '');
    }

    /**
     * Sucht die erste der übergebenen, bekannten (offenen) Rechnungsnummern,
     * die normalisiert als Teilstring im normalisierten $text vorkommt.
     *
     * @param string[] $knownInvoiceNumbers
     */
    public static function findInvoiceNumberInText(string $text, array $knownInvoiceNumbers): ?string
    {
        $normalizedText = self::normalize($text);
        if ($normalizedText === '') {
            return null;
        }

        foreach ($knownInvoiceNumbers as $invoiceNumber) {
            $normalizedNumber = self::normalize($invoiceNumber);
            if ($normalizedNumber !== '' && str_contains($normalizedText, $normalizedNumber)) {
                return $invoiceNumber;
            }
        }

        return null;
    }
}
