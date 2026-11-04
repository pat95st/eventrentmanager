<?php

declare(strict_types=1);

namespace App\Application\Rack\Command;

use App\Application\Command\CommandInterface;

/** Feste Gerätezuordnung und Anschaffungsdatum eines Rack-Exemplars ändern. */
final class UpdateRackExemplar implements CommandInterface
{
    public function __construct(
        public readonly string $exemplarId,
        public readonly ?string $fixedInventoryNumberId,
        public readonly ?string $acquisitionDate,
    ) {
    }
}
