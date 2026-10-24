<?php

declare(strict_types=1);

namespace App\Application\Reporting\Query;

use App\Application\Query\QueryInterface;

/** Einnahmen-/Ausgabenübersicht für ein Kalenderjahr (siehe GetIncomeExpenseOverviewHandler für die genaue Berechnungsgrundlage). */
final class GetIncomeExpenseOverview implements QueryInterface
{
    public function __construct(public readonly int $year)
    {
    }
}
