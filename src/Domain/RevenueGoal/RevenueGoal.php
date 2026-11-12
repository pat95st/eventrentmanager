<?php

declare(strict_types=1);

namespace App\Domain\RevenueGoal;

/**
 * Umsatzziel bzw. Umsatzgrenze (Migration 146). $amount gilt für jedes
 * Jahr; $yearAmounts enthält davon abweichende Beträge einzelner Jahre.
 * Beträge als String (DECIMAL).
 */
final class RevenueGoal
{
    /** @param array<int, string> $yearAmounts Jahr => abweichender Betrag */
    public function __construct(
        public readonly string $id,
        public string $name,
        public RevenueGoalKind $kind,
        public string $amount,
        public array $yearAmounts = [],
    ) {
    }

    public function amountFor(int $year): string
    {
        return $this->yearAmounts[$year] ?? $this->amount;
    }

    public function hasOverrideFor(int $year): bool
    {
        return isset($this->yearAmounts[$year]);
    }

    /** Abweichenden Betrag eines Jahres setzen; null entfernt ihn (es gilt wieder der Standardbetrag). */
    public function setYearAmount(int $year, ?string $amount): void
    {
        if ($amount === null) {
            unset($this->yearAmounts[$year]);

            return;
        }
        $this->yearAmounts[$year] = $amount;
        ksort($this->yearAmounts);
    }
}
