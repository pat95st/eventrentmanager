<?php

declare(strict_types=1);

namespace App\Application\Datev;

use DateTimeImmutable;

/** Fertiges DATEV-Paket (ZIP) samt Kennzahlen, siehe DatevExportBuilder. */
final class DatevExportPackage
{
    /** @param string[] $warnings */
    public function __construct(
        public readonly DateTimeImmutable $from,
        public readonly DateTimeImmutable $to,
        public readonly string $filename,
        public readonly string $zipContent,
        public readonly int $invoiceCount,
        public readonly int $correctionCount,
        public readonly int $receiptCount,
        public readonly int $documentCount,
        public readonly array $warnings,
    ) {
    }

    public function bookingCount(): int
    {
        return $this->invoiceCount + $this->correctionCount + $this->receiptCount;
    }
}
