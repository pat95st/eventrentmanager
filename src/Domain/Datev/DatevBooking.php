<?php

declare(strict_types=1);

namespace App\Domain\Datev;

use DateTimeImmutable;

/**
 * Ein Buchungssatz im DATEV-Buchungsstapel. Der Betrag ist immer positiv,
 * die Richtung steckt im Soll/Haben-Kennzeichen, das sich (DATEV-Konvention)
 * auf $account bezieht; $contraAccount wird jeweils gegengebucht.
 */
final class DatevBooking
{
    /** @param array<string, string> $documentInfo Beleginfo: Art => Inhalt (max. 8) */
    public function __construct(
        public readonly float $amount,
        public readonly bool $isDebit,
        public readonly int $account,
        public readonly int $contraAccount,
        public readonly ?string $taxKey,
        public readonly DateTimeImmutable $documentDate,
        public readonly string $documentField1,
        public readonly ?string $documentField2,
        public readonly string $text,
        public readonly ?DateTimeImmutable $serviceDate = null,
        public readonly array $documentInfo = [],
    ) {
    }

    /**
     * Baut die Buchung aus einem vorzeichenbehafteten Betrag: positiv =
     * $account im Soll, negativ = $account im Haben (z. B. Gutschrift).
     *
     * @param array<string, string> $documentInfo
     */
    public static function signed(
        float $signedAmount,
        int $account,
        int $contraAccount,
        ?string $taxKey,
        DateTimeImmutable $documentDate,
        string $documentField1,
        ?string $documentField2,
        string $text,
        ?DateTimeImmutable $serviceDate = null,
        array $documentInfo = [],
    ): self {
        return new self(
            amount: round(abs($signedAmount), 2),
            isDebit: $signedAmount >= 0,
            account: $account,
            contraAccount: $contraAccount,
            taxKey: $taxKey,
            documentDate: $documentDate,
            documentField1: $documentField1,
            documentField2: $documentField2,
            text: $text,
            serviceDate: $serviceDate,
            documentInfo: $documentInfo,
        );
    }
}
