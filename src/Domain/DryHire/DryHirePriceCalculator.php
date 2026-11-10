<?php

declare(strict_types=1);

namespace App\Domain\DryHire;

use App\Domain\Settings\PriceRoundingDirection;
use App\Domain\Settings\PriceRoundingStep;

/**
 * Dry-Hire-Staffel (Migration 141): Mietpreis für N Tage = Summe der
 * Tagesanteile; Tag d kostet den Prozentsatz der höchsten Stufe mit
 * fromDay <= d. Beispiel 1:100, 2:50, 8:25 → 3 Tage = 100+50+50 = 200 %.
 * Gilt für beliebig lange Mietdauer (anders als die normale Staffel).
 */
final class DryHirePriceCalculator
{
    /** @param DryHireTier[] $tiers @return float Faktor bezogen auf den Tagesmietsatz (2.0 = 200 %) */
    public static function factor(int $days, array $tiers): float
    {
        $days = max(1, $days);
        usort($tiers, static fn (DryHireTier $a, DryHireTier $b) => $a->fromDay <=> $b->fromDay);
        if ($tiers === [] || $tiers[0]->fromDay !== 1) {
            array_unshift($tiers, new DryHireTier(1, '100'));
        }
        $total = 0.0;
        $count = count($tiers);
        for ($i = 0; $i < $count; $i++) {
            $from = $tiers[$i]->fromDay;
            if ($from > $days) {
                break;
            }
            $to = $i + 1 < $count ? min($days, $tiers[$i + 1]->fromDay - 1) : $days;
            $total += ($to - $from + 1) * ((float) $tiers[$i]->percentage) / 100;
        }

        return round($total, 6);
    }

    /** @param DryHireTier[] $tiers */
    public static function price(string $dailyPrice, int $days, array $tiers, PriceRoundingStep $step = PriceRoundingStep::None, PriceRoundingDirection $direction = PriceRoundingDirection::Nearest): string
    {
        $price = ((float) $dailyPrice) * self::factor($days, $tiers);
        $stepAmount = $step->amount();
        if ($stepAmount !== null && $stepAmount > 0) {
            $units = round($price / $stepAmount, 6);
            $units = match ($direction) {
                PriceRoundingDirection::Up => ceil($units),
                PriceRoundingDirection::Down => floor($units),
                PriceRoundingDirection::Nearest => round($units),
            };
            $price = $units * $stepAmount;
        }

        return number_format(round($price, 2), 2, '.', '');
    }
}
