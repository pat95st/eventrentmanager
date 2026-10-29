<?php

declare(strict_types=1);

namespace App\Domain\Settings;

use DateTimeImmutable;

/**
 * Eine Bankverbindung der Firma (Migration 105). Es können beliebig viele
 * hinterlegt werden; genau eine ist das Standardkonto ($isDefault), das auf
 * Rechnungen erscheint, sofern dort kein anderes Konto gewählt wurde (siehe
 * Invoice::$bankAccountId).
 */
final class BankAccount
{
    public function __construct(
        public readonly string $id,
        public string $label,          // interne Bezeichnung für die Auswahl, z. B. "Sparkasse Geschäftskonto"
        public ?string $accountHolder, // Kontoinhaber, falls abweichend vom Firmennamen
        public ?string $bankName,
        public string $iban,           // normalisiert: Großbuchstaben, ohne Leerzeichen
        public ?string $bic,
        public bool $isDefault,
        public readonly DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {
    }

    /** IBAN in 4er-Gruppen für die Anzeige, z. B. "DE89 3704 0044 0532 0130 00". */
    public function formattedIban(): string
    {
        return trim(chunk_split($this->iban, 4, ' '));
    }
}
