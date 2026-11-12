<?php

declare(strict_types=1);

namespace App\Application\Depreciation;

use App\Application\Query\QueryInterface;

/** Abschreibungsplan einer einzelnen Inventarnummer (Ergebnis: DepreciationSchedule oder null, wenn Anschaffungsdatum/Einkaufspreis fehlen). */
final class GetInventoryNumberDepreciation implements QueryInterface
{
    public function __construct(public readonly string $inventoryNumberId)
    {
    }
}
