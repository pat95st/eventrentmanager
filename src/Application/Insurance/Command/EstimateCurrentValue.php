<?php

declare(strict_types=1);

namespace App\Application\Insurance\Command;

use App\Application\Command\CommandInterface;

/** Zeitwert eines Exemplars per KI schätzen und speichern. Liefert den gespeicherten CurrentValue. */
final class EstimateCurrentValue implements CommandInterface
{
    public function __construct(public readonly string $inventoryNumberId)
    {
    }
}
