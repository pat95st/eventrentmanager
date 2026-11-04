<?php

declare(strict_types=1);

namespace App\Application\Statistics\Query;

use App\Application\Query\QueryInterface;
use App\Application\Statistics\StatisticKey;

/** Eine Statistik; $detailed liefert mehr Einträge (Detailseite) statt der Kurzfassung (Dashboard). */
final class GetStatistic implements QueryInterface
{
    public function __construct(
        public readonly StatisticKey $key,
        public readonly int $months = 12,
        public readonly bool $detailed = false,
    ) {
    }
}
