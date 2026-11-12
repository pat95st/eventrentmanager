<?php

declare(strict_types=1);

namespace App\Application\Depreciation;

use App\Application\Query\QueryInterface;

/** Abschreibungen aller Inventarnummern für ein Kalenderjahr (Ergebnis: DepreciationReport). */
final class GetDepreciationReport implements QueryInterface
{
    public function __construct(public readonly int $year)
    {
    }
}
