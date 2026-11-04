<?php

declare(strict_types=1);

namespace App\Application\ElectricalInspection\Query;

use App\Application\Query\QueryInterface;

/** Zuletzt erfasste Elektroprüfungen aller Geräte (Seite „Elektroprüfung“). */
final class ListRecentElectricalInspections implements QueryInterface
{
    public function __construct(
        public readonly ?string $search = null,
        public readonly int $limit = 100,
    ) {
    }
}
