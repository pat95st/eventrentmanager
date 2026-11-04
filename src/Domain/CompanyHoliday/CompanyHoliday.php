<?php

declare(strict_types=1);

namespace App\Domain\CompanyHoliday;

use DateTimeImmutable;

/**
 * Betriebsurlaub (Migration 130): ganztägiger Zeitraum [startDate, endDate]
 * (beide inklusive), in dem keine Ausgabe und keine Rücknahme stattfindet.
 */
final class CompanyHoliday
{
    public function __construct(
        public readonly string $id,
        public DateTimeImmutable $startDate,
        public DateTimeImmutable $endDate,
        public ?string $note,
        public readonly DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {
    }

    /** Überschneidet sich der (ganztägige) Zeitraum [$from, $to] mit diesem Urlaub? */
    public function overlaps(DateTimeImmutable $from, DateTimeImmutable $to): bool
    {
        return $from->format('Y-m-d') <= $this->endDate->format('Y-m-d')
            && $to->format('Y-m-d') >= $this->startDate->format('Y-m-d');
    }

    /** z. B. "22.12.2026 – 06.01.2027 (Weihnachten)" */
    public function label(): string
    {
        $range = $this->startDate->format('d.m.Y') === $this->endDate->format('d.m.Y')
            ? $this->startDate->format('d.m.Y')
            : $this->startDate->format('d.m.Y') . ' – ' . $this->endDate->format('d.m.Y');

        return $range . ($this->note !== null && $this->note !== '' ? ' (' . $this->note . ')' : '');
    }

    /** Letzter möglicher Abholtag (Tag vor dem Urlaub). */
    public function lastPickupDay(): DateTimeImmutable
    {
        return $this->startDate->modify('-1 day');
    }

    /** Erster möglicher Rückgabetag (Tag nach dem Urlaub). */
    public function firstReturnDay(): DateTimeImmutable
    {
        return $this->endDate->modify('+1 day');
    }
}
