<?php

declare(strict_types=1);

namespace App\Application\Statistics\Query;

use App\Application\Statistics\StatisticKey;

/**
 * Daten einer Statistik für Chart.js und die Tabelle der Detailseite.
 *
 * $type: 'bar' | 'line' | 'doughnut' | 'barH' (waagerechte Balken).
 * $datasets: je Reihe label, data (Zahlen je $labels-Eintrag), optional
 * type (für gemischte Diagramme) und color (Index in die Farbpalette).
 * $rows: Tabelle der Detailseite, je Zeile Spaltenwerte; $links optional je
 * Zeile ein Link.
 */
final class StatisticChart
{
    /**
     * @param list<string> $labels
     * @param list<array{label: string, data: list<float|int>, type?: string, color?: int}> $datasets
     * @param list<string> $columns
     * @param list<list<string>> $rows
     * @param list<?string> $links
     */
    public function __construct(
        public readonly StatisticKey $key,
        public readonly string $type,
        public readonly array $labels,
        public readonly array $datasets,
        public readonly string $headline,
        public readonly string $headlineCaption,
        public readonly array $columns = [],
        public readonly array $rows = [],
        public readonly array $links = [],
        public readonly bool $currency = false,
    ) {
    }

    /** Für data-chart: alles, was das Diagramm-Skript braucht. */
    public function toChartJson(): string
    {
        return (string) json_encode([
            'type' => $this->type,
            'labels' => $this->labels,
            'datasets' => $this->datasets,
            'currency' => $this->currency,
        ], JSON_UNESCAPED_UNICODE);
    }

    public function isEmpty(): bool
    {
        foreach ($this->datasets as $dataset) {
            foreach ($dataset['data'] as $value) {
                if ((float) $value !== 0.0) {
                    return false;
                }
            }
        }

        return true;
    }
}
