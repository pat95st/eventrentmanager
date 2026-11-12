<?php

declare(strict_types=1);

namespace App\Application\Datev;

use App\Domain\Datev\DatevBooking;
use DateTimeImmutable;

/** Buchungen eines Wirtschaftsjahres innerhalb des Exportzeitraums (= eine EXTF-Datei). */
final class DatevBatch
{
    /** @param DatevBooking[] $bookings */
    public function __construct(
        public readonly DateTimeImmutable $fiscalYearStart,
        public readonly DateTimeImmutable $from,
        public readonly DateTimeImmutable $to,
        public array $bookings = [],
    ) {
    }
}
