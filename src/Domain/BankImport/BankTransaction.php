<?php

declare(strict_types=1);

namespace App\Domain\BankImport;

use DateTimeImmutable;

/**
 * Unveränderliches Wertobjekt für eine einzelne, aus einem Kontoauszug
 * (MT940 oder CAMT.053) extrahierte Buchung. Der Betrag wird bewusst als
 * String mit genau zwei Nachkommastellen gehalten (kein float), um
 * Rundungsfehler beim späteren Abgleich mit Rechnungsbeträgen zu vermeiden.
 */
final class BankTransaction
{
    public function __construct(
        public readonly DateTimeImmutable $bookingDate,
        public readonly string $amount,
        public readonly string $currency,
        public readonly bool $isCredit,
        public readonly ?string $purpose = null,
        public readonly ?string $counterpartyName = null,
        public readonly ?string $reference = null,
    ) {
    }
}
