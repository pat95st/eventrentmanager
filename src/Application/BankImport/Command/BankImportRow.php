<?php

declare(strict_types=1);

namespace App\Application\BankImport\Command;

/**
 * Eine Zeile des Zahlungsabgleichs: eine geparste Gutschrift plus (falls
 * vorhanden) der automatisch gefundene Zuordnungsvorschlag. Reines
 * Lese-/Anzeigemodell für die Review-Seite — die tatsächlich maßgeblichen
 * Daten liegen in der Zwischenablage (siehe BankImportStaging) und werden
 * beim Bestätigen erneut aus dem Rohwert ($invoiceNumberGuess als
 * vorbelegter, aber vom Nutzer überschreibbarer Formularwert) verwendet.
 */
final class BankImportRow
{
    public function __construct(
        public readonly int $rowIndex,
        public readonly string $bookingDate, // Y-m-d
        public readonly string $amount,
        public readonly string $currency,
        public readonly bool $isCredit,
        public readonly ?string $purpose,
        public readonly ?string $counterpartyName,
        public readonly ?string $reference,
        // Automatischer Zuordnungsvorschlag (null, wenn keine offene
        // Rechnung im Verwendungszweck gefunden wurde, oder wenn es sich
        // nicht um eine Gutschrift handelt).
        public readonly ?string $matchedInvoiceNumber,
        public readonly ?string $matchedCustomerName,
        public readonly ?string $matchedGrossTotal,
        // true, wenn Buchungsbetrag und Rechnungsbetrag voneinander
        // abweichen (nur relevant, wenn ein Treffer vorliegt) — für die
        // farbliche Hervorhebung auf der Review-Seite.
        public readonly bool $amountMismatch,
    ) {
    }
}
