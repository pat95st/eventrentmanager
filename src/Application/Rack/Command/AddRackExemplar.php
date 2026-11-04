<?php

declare(strict_types=1);

namespace App\Application\Rack\Command;

use App\Application\Command\CommandInterface;

/** Neues Exemplar eines Rack-Typs; Rack- und Inventurnummer werden vergeben (Migration 124). */
final class AddRackExemplar implements CommandInterface
{
    public function __construct(
        public readonly string $rackId,
        public readonly ?string $acquisitionDate = null,
        public readonly ?string $fixedInventoryNumberId = null,
    ) {
    }
}
