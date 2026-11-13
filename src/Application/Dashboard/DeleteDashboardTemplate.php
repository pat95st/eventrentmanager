<?php

declare(strict_types=1);

namespace App\Application\Dashboard;

use App\Application\Command\CommandInterface;

/** Löscht eine Vorlage. Bereits übernommene Anordnungen der Benutzer bleiben bestehen (Kopie). */
final class DeleteDashboardTemplate implements CommandInterface
{
    public function __construct(public readonly string $id)
    {
    }
}
