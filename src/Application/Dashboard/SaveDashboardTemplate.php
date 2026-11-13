<?php

declare(strict_types=1);

namespace App\Application\Dashboard;

use App\Application\Command\CommandInterface;

/** Legt eine Vorlage für die Startseite an ($id === null) oder ändert sie. Nur für Administratoren (Routen-Guard). */
final class SaveDashboardTemplate implements CommandInterface
{
    /** @param array<mixed> $zones ungeprüfte Formulardaten: Bereich => Kachel-Schlüssel */
    public function __construct(
        public readonly ?string $id,
        public readonly string $name,
        public readonly string $description,
        public readonly array $zones,
    ) {
    }
}
