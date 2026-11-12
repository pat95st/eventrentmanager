<?php

declare(strict_types=1);

namespace App\Application\Depreciation;

use App\Domain\Depreciation\DepreciationAsset;

/**
 * Abschreibungsübersicht eines Jahres: alle Exemplare, die in dem Jahr
 * (zumindest zeitweise) im Bestand waren, plus die Exemplare, für die
 * mangels Anschaffungsdatum oder Einkaufspreis nichts berechnet werden kann.
 */
final class DepreciationReport
{
    /**
     * @param DepreciationReportRow[] $rows
     * @param DepreciationAsset[] $incomplete
     */
    public function __construct(
        public readonly int $year,
        public readonly array $rows,
        public readonly array $incomplete,
    ) {
    }

    public function totalCost(): float
    {
        return $this->sum(static fn (DepreciationReportRow $r) => $r->schedule->cost);
    }

    public function totalDepreciation(): float
    {
        return $this->sum(fn (DepreciationReportRow $r) => $r->schedule->amountIn($this->year));
    }

    public function totalDisposal(): float
    {
        return $this->sum(fn (DepreciationReportRow $r) => $r->schedule->disposalIn($this->year));
    }

    public function totalBookValueStart(): float
    {
        return $this->sum(fn (DepreciationReportRow $r) => $r->schedule->bookValueAtStartOf($this->year));
    }

    public function totalBookValueEnd(): float
    {
        return $this->sum(fn (DepreciationReportRow $r) => $r->schedule->bookValueAtEndOf($this->year));
    }

    /** @param callable(DepreciationReportRow): float $value */
    private function sum(callable $value): float
    {
        return round(array_sum(array_map($value, $this->rows)), 2);
    }
}
