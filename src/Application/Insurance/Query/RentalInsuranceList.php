<?php

declare(strict_types=1);

namespace App\Application\Insurance\Query;

use App\Domain\Customer\Customer;
use App\Domain\Rental\Rental;

final class RentalInsuranceList
{
    /** @param list<InsuranceListRow> $rows */
    public function __construct(
        public readonly Rental $rental,
        public readonly Customer $customer,
        public readonly array $rows,
    ) {
    }

    /** Summe der vorhandenen Zeitwerte (fehlende zählen nicht mit), als "1234.50". */
    public function totalCurrentValue(): string
    {
        return $this->sum(static fn (InsuranceListRow $r) => $r->currentValue);
    }

    /** Summe der vorhandenen Anschaffungspreise, als "1234.50". */
    public function totalPurchasePrice(): string
    {
        return $this->sum(static fn (InsuranceListRow $r) => $r->purchasePrice);
    }

    public function countMissingCurrentValues(): int
    {
        return count(array_filter($this->rows, static fn (InsuranceListRow $r) => $r->currentValue === null));
    }

    public function countMissingPurchasePrices(): int
    {
        return count(array_filter($this->rows, static fn (InsuranceListRow $r) => $r->purchasePrice === null));
    }

    /** @param callable(InsuranceListRow): ?string $amount */
    private function sum(callable $amount): string
    {
        // In Cent rechnen, damit sich keine Float-Rundungsfehler aufsummieren.
        $cents = 0;
        foreach ($this->rows as $row) {
            $value = $amount($row);
            if ($value !== null) {
                $cents += (int) round(((float) $value) * 100);
            }
        }

        return number_format($cents / 100, 2, '.', '');
    }
}
