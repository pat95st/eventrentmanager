<?php

declare(strict_types=1);

namespace App\Application\Calendar\Command;

use App\Application\Command\CommandInterface;

/** $userId: Eigentümer-Prüfung im Handler — ein User darf nur seine eigenen Zugangsdaten löschen. */
final class DeleteCalDavCredential implements CommandInterface
{
    public function __construct(
        public readonly string $id,
        public readonly string $userId,
    ) {
    }
}
