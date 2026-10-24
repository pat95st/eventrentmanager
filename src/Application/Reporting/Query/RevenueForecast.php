<?php

declare(strict_types=1);

namespace App\Application\Reporting\Query;

/** Ergebnis von GetRevenueForecast: ein RevenueForecastYear pro angefragtem Kalenderjahr, chronologisch aufsteigend. */
final class RevenueForecast
{
    /** @param RevenueForecastYear[] $years */
    public function __construct(public readonly array $years)
    {
    }

    public function grandTotal(): float
    {
        return array_sum(array_map(static fn (RevenueForecastYear $y) => $y->total(), $this->years));
    }

    /** Für die Balken-Visualisierung: größter Jahres-Gesamtwert, mindestens 1 (verhindert Division durch 0). */
    public function maxYearValue(): float
    {
        $max = 0.0;
        foreach ($this->years as $y) {
            $max = max($max, $y->total());
        }

        return max($max, 1.0);
    }
}
