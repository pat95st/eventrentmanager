<?php

declare(strict_types=1);

namespace App\Application\Reporting\Query;

/** Einnahmen/Ausgaben/Saldo eines einzelnen Monats — Teil von IncomeExpenseOverview. */
final class IncomeExpenseMonth
{
    public function __construct(
        public readonly int $month, // 1-12
        public readonly float $income,
        public readonly float $expense,
    ) {
    }

    public function saldo(): float
    {
        return $this->income - $this->expense;
    }
}
