<?php

declare(strict_types=1);

namespace App\Domain\Depreciation;

use DateTimeImmutable;

/**
 * Lineare Abschreibung (AfA) eines Exemplars aus Anschaffungskosten,
 * Anschaffungsdatum und Nutzungsdauer:
 *
 *  - Liegen die Anschaffungskosten bis einschließlich der GWG-Grenze
 *    (DepreciationSettings::$lowValueThreshold, 0 = aus), wird im
 *    Anschaffungsjahr vollständig abgeschrieben.
 *  - Sonst gleichmäßig über Nutzungsdauer × 12 Monate. Im Anschaffungsjahr
 *    je nach Einstellung monatsgenau (Anschaffungsmonat zählt mit) oder mit
 *    voller Jahresrate. Abgeschrieben wird bis auf den Erinnerungswert.
 *  - Bei einem Verkauf endet die Abschreibung mit dem Monat vor dem
 *    Verkaufsmonat; der verbleibende Restbuchwert wird im Verkaufsjahr
 *    ausgebucht (DepreciationYear::$disposal).
 *
 * Gerechnet wird in Cent über die kumulierte Abschreibung, damit sich die
 * Jahresbeträge trotz Rundung exakt zu den Anschaffungskosten summieren.
 */
final class DepreciationCalculator
{
    public static function schedule(
        string $cost,
        DateTimeImmutable $acquisitionDate,
        int $usefulLifeYears,
        DepreciationSettings $settings,
        ?DateTimeImmutable $disposalDate = null,
    ): DepreciationSchedule {
        $costCents = max(0, (int) round((float) $cost * 100));
        $usefulLifeYears = max(1, $usefulLifeYears);
        $thresholdCents = (int) round((float) $settings->lowValueThreshold * 100);
        $lowValue = $thresholdCents > 0 && $costCents <= $thresholdCents;

        $acqYear = (int) $acquisitionDate->format('Y');
        $acqMonth = (int) $acquisitionDate->format('n');
        $fullYear = $settings->firstYearMode === DepreciationFirstYearMode::FullYear;

        $depreciable = $lowValue ? $costCents : max(0, $costCents - (int) round((float) $settings->residualValue * 100));
        $totalMonths = $lowValue ? 1 : $usefulLifeYears * 12;

        // Kumulierte Abschreibung (Cent) nach $months Abschreibungsmonaten.
        $accumulated = static fn (int $months): int => (int) round($depreciable * min(max($months, 0), $totalMonths) / $totalMonths);
        // Abschreibungsmonate bis zum Ende des Jahres $year.
        $monthsUntilEndOf = static function (int $year) use ($acqYear, $acqMonth, $fullYear, $lowValue): int {
            if ($year < $acqYear) {
                return 0;
            }
            if ($lowValue) {
                return 1;
            }

            return ($year - $acqYear) * 12 + ($fullYear ? 12 : 13 - $acqMonth);
        };

        $disposalYear = null;
        $accumulatedAtDisposal = null;
        if ($disposalDate !== null && $disposalDate >= $acquisitionDate->setTime(0, 0)) {
            $disposalYear = (int) $disposalDate->format('Y');
            $disposalMonth = (int) $disposalDate->format('n');
            $months = $lowValue ? 1 : ($disposalYear - $acqYear) * 12 + $disposalMonth - ($fullYear ? 1 : $acqMonth);
            // Nie weniger, als bis zum Vorjahresende bereits abgeschrieben wurde.
            $accumulatedAtDisposal = max($accumulated($months), $accumulated($monthsUntilEndOf($disposalYear - 1)));
        }

        $years = [];
        $previous = 0;
        for ($year = $acqYear; $year <= $acqYear + $usefulLifeYears + 1; $year++) {
            $isDisposalYear = $year === $disposalYear;
            $current = $isDisposalYear ? (int) $accumulatedAtDisposal : $accumulated($monthsUntilEndOf($year));
            $disposal = $isDisposalYear ? $costCents - $current : 0;
            $years[$year] = new DepreciationYear(
                $year,
                ($current - $previous) / 100,
                $disposal / 100,
                $isDisposalYear ? 0.0 : ($costCents - $current) / 100,
            );
            $previous = $current;
            if ($isDisposalYear || $current >= $depreciable) {
                break;
            }
        }

        return new DepreciationSchedule(
            $costCents / 100,
            $acquisitionDate,
            $usefulLifeYears,
            $lowValue,
            $disposalYear !== null ? $disposalDate : null,
            $years,
        );
    }
}
