<?php

declare(strict_types=1);

namespace App\Application\Technician\Command;

use App\Application\Command\CommandInterface;

/** Gewerke/Hinweis eines Technikers an einer Veranstaltung ändern. */
final class UpdateRentalTechnician implements CommandInterface
{
    public function __construct(
        public readonly string $id,
        /** @var string[] */
        public readonly array $tradeIds,
        public readonly ?string $note = null,
    ) {
    }
}
