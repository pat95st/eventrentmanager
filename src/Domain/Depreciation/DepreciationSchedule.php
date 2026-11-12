<?php

declare(strict_types=1);

namespace App\Domain\Depreciation;

use DateTimeImmutable;

/**
 * Abschreibungsplan eines einzelnen Exemplars: je Kalenderjahr die
 * Abschreibung und der Restbuchwert, vom Anschaffungsjahr bis zum Jahr der
 * letzten Abschreibung (bzw. des Verkaufs). Erzeugt von
 * DepreciationCalculator::schedule().
 */
final class DepreciationSchedule
{
    /**
     * @param array<int, DepreciationYear> $years Jahr => Eintrag, lückenlos aufsteigend
     */
    public function __construct(
        public readonly float $cost,
        public readonly DateTimeImmutable $acquisitionDate,
        public readonly int $usefulLifeYears,
        public readonly bool $lowValueAsset, // als geringwertiges Wirtschaftsgut sofort abgeschrieben
        public readonly ?DateTimeImmutable $disposalDate,
        public readonly array $years,
    ) {
    }

    public function firstYear(): int
    {
        return (int) $this->acquisitionDate->format('Y');
    }

    public function lastYear(): int
    {
        return (int) array_key_last($this->years);
    }

    /** War das Exemplar in diesem Jahr (zumindest zeitweise) im Bestand? */
    public function coversYear(int $year): bool
    {
        if ($year < $this->firstYear()) {
            return false;
        }

        return $this->disposalDate === null || $year <= (int) $this->disposalDate->format('Y');
    }

    public function amountIn(int $year): float
    {
        return isset($this->years[$year]) ? $this->years[$year]->amount : 0.0;
    }

    public function disposalIn(int $year): float
    {
        return isset($this->years[$year]) ? $this->years[$year]->disposal : 0.0;
    }

    /** Restbuchwert am Ende des Jahres; vor der Anschaffung 0, nach dem letzten Planjahr dessen Endwert. */
    public function bookValueAtEndOf(int $year): float
    {
        if ($year < $this->firstYear()) {
            return 0.0;
        }
        if (isset($this->years[$year])) {
            return $this->years[$year]->bookValueEnd;
        }

        return $this->years[$this->lastYear()]->bookValueEnd;
    }

    /** Restbuchwert zu Beginn des Jahres (im Anschaffungsjahr die Anschaffungskosten). */
    public function bookValueAtStartOf(int $year): float
    {
        if ($year < $this->firstYear()) {
            return 0.0;
        }

        return $year === $this->firstYear() ? $this->cost : $this->bookValueAtEndOf($year - 1);
    }

    /** Bis einschließlich dieses Jahres aufgelaufene Abschreibung (ohne ausgebuchten Restbuchwert). */
    public function accumulatedUntil(int $year): float
    {
        $sum = 0.0;
        foreach ($this->years as $entry) {
            if ($entry->year <= $year) {
                $sum += $entry->amount;
            }
        }

        return round($sum, 2);
    }
}
