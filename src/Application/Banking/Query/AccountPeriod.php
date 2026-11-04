<?php

declare(strict_types=1);

namespace App\Application\Banking\Query;

use DateTimeImmutable;

/**
 * Angezeigter Zeitraum der Kontoführung (Migration 131): ein Monat, ein
 * Kalenderjahr oder fünf Kalenderjahre (endend mit dem Jahr von $anchor).
 * Diagramm und Buchungsliste folgen beide diesem Zeitraum.
 */
final class AccountPeriod
{
    public const MONTH = 'monat';
    public const YEAR = 'jahr';
    public const YEARS = 'jahre';
    public const YEARS_SPAN = 5;

    private const MONTHS = ['', 'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];

    public readonly DateTimeImmutable $from;
    public readonly DateTimeImmutable $to;

    public function __construct(public readonly string $mode, public readonly DateTimeImmutable $anchor)
    {
        $year = (int) $anchor->format('Y');
        [$this->from, $this->to] = match ($mode) {
            self::MONTH => [$anchor->modify('first day of this month')->setTime(0, 0), $anchor->modify('last day of this month')->setTime(0, 0)],
            self::YEARS => [new DateTimeImmutable(($year - self::YEARS_SPAN + 1) . '-01-01'), new DateTimeImmutable($year . '-12-31')],
            default => [new DateTimeImmutable($year . '-01-01'), new DateTimeImmutable($year . '-12-31')],
        };
    }

    /** Aus den Query-Parametern ?zeitraum=monat|jahr|jahre&datum=Y-m-d (Standard: aktuelles Jahr). */
    public static function fromQuery(?string $mode, ?string $date): self
    {
        $mode = in_array($mode, [self::MONTH, self::YEAR, self::YEARS], true) ? $mode : self::YEAR;
        $anchor = $date !== null ? DateTimeImmutable::createFromFormat('!Y-m-d', $date) : false;

        return new self($mode, $anchor !== false ? $anchor : new DateTimeImmutable('today'));
    }

    public function previous(): self
    {
        return new self($this->mode, $this->mode === self::MONTH ? $this->from->modify('-1 month') : $this->from->modify('first day of january')->modify($this->mode === self::YEARS ? '+' . (self::YEARS_SPAN - 2) . ' years' : '-1 year'));
    }

    public function next(): self
    {
        return new self($this->mode, $this->mode === self::MONTH ? $this->from->modify('+1 month') : $this->to->modify('+1 day'));
    }

    /** Gleicher Zeitraum-Typ, aber mit heutigem Datum. */
    public function current(): self
    {
        return new self($this->mode, new DateTimeImmutable('today'));
    }

    public function withMode(string $mode): self
    {
        $today = new DateTimeImmutable('today');
        // Beim Wechsel möglichst einen sinnvollen Bezugstag behalten.
        $anchor = $today >= $this->from && $today <= $this->to ? $today : $this->to;

        return new self($mode, $anchor);
    }

    public function label(): string
    {
        return match ($this->mode) {
            self::MONTH => self::MONTHS[(int) $this->from->format('n')] . ' ' . $this->from->format('Y'),
            self::YEARS => $this->from->format('Y') . ' – ' . $this->to->format('Y'),
            default => $this->from->format('Y'),
        };
    }

    /** @return array{zeitraum: string, datum: string} */
    public function query(): array
    {
        return ['zeitraum' => $this->mode, 'datum' => $this->anchor->format('Y-m-d')];
    }
}
