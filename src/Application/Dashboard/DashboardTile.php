<?php

declare(strict_types=1);

namespace App\Application\Dashboard;

use App\Application\Statistics\StatisticKey;
use App\Domain\Dashboard\DashboardWidget;
use App\Domain\User\Permission;

/**
 * Eine Kachel der Startseite: entweder einer der Bereiche aus
 * DashboardWidget oder das Diagramm einer Statistik (StatisticKey).
 */
final class DashboardTile
{
    /** Lagerplan der nächsten 14 Tage. */
    public const string KIND_SCHEDULE = 'schedule';
    /** Zähler mit kurzer Liste bzw. Kennzahl. */
    public const string KIND_COMPACT = 'compact';
    /** Diagramm einer Statistik. */
    public const string KIND_CHART = 'chart';

    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $description,
        public readonly Permission $requiredPermission,
        public readonly string $kind,
        public readonly ?DashboardWidget $widget = null,
        public readonly ?StatisticKey $statistic = null,
    ) {
    }
}
