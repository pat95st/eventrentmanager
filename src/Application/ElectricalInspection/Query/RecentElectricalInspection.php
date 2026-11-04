<?php

declare(strict_types=1);

namespace App\Application\ElectricalInspection\Query;

use App\Domain\ElectricalInspection\ElectricalInspection;

/** Eintrag der Prüfungsliste: Prüfung mit Gerät (ohne Preise/Belege). */
final class RecentElectricalInspection
{
    public function __construct(
        public readonly ElectricalInspection $inspection,
        public readonly string $inventoryNumberId,
        public readonly string $inventoryNumber,
        public readonly string $articleLabel,
    ) {
    }
}
