<?php

declare(strict_types=1);

namespace App\Domain\Dashboard;

/**
 * Von einem Administrator angelegte Vorlage für die Startseite (Migration
 * 147). Benutzer übernehmen sie unter /profil/dashboard als Kopie in ihre
 * eigene Anordnung – dabei bleiben nur die Kacheln übrig, für die sie das
 * nötige Recht haben (siehe App\Application\Dashboard\DashboardLayoutCommandHandler).
 */
final class DashboardTemplate
{
    public function __construct(
        public readonly string $id,
        public string $name,
        public ?string $description,
        public DashboardLayout $layout,
    ) {
    }
}
