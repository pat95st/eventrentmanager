<?php

declare(strict_types=1);

namespace App\Application\Dashboard;

use App\Application\Command\CommandInterface;

/** Übernimmt eine Vorlage als Kopie in die Anordnung des Benutzers – ohne die Kacheln, die er nicht sehen darf. */
final class ApplyDashboardTemplate implements CommandInterface
{
    public function __construct(
        public readonly string $userId,
        public readonly string $templateId,
    ) {
    }
}
