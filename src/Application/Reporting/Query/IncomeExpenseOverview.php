<?php

declare(strict_types=1);

namespace App\Application\Reporting\Query;

/**
 * Ergebnis von GetIncomeExpenseOverview: 12 Monate (Januar-Dezember,
 * IMMER alle 12 vorhanden, auch ohne Buchungen — mit 0.0 befüllt, damit die
 * Ansicht nicht selbst Lücken auffüllen muss) plus Jahressummen und eine
 * Aufschlüsselung der Ausgaben nach Beleg-Kategorie (siehe ReceiptCategory).
 */
final class IncomeExpenseOverview
{
    /**
     * @param IncomeExpenseMonth[] $months genau 12 Einträge, Index 0 = Januar
     * @param array<string, float> $expenseByCategory Kategorie-Wert (ReceiptCategory::value) => Summe
     */
    public function __construct(
        public readonly int $year,
        public readonly array $months,
        public readonly array $expenseByCategory,
    ) {
    }

    public function totalIncome(): float
    {
        return array_sum(array_map(fn (IncomeExpenseMonth $m) => $m->income, $this->months));
    }

    public function totalExpense(): float
    {
        return array_sum(array_map(fn (IncomeExpenseMonth $m) => $m->expense, $this->months));
    }

    public function totalSaldo(): float
    {
        return $this->totalIncome() - $this->totalExpense();
    }

    /** Für die Balken-Visualisierung: größter Einzelwert (Einnahme ODER Ausgabe) über alle Monate, mindestens 1 (verhindert Division durch 0). */
    public function maxMonthValue(): float
    {
        $max = 0.0;
        foreach ($this->months as $m) {
            $max = max($max, $m->income, $m->expense);
        }

        return max($max, 1.0);
    }
}
