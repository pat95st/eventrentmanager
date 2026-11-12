<?php

declare(strict_types=1);

namespace App\Application\Datev\Command;

use App\Application\Command\CommandInterface;
use App\Domain\Datev\DatevExportTrigger;
use DateTimeImmutable;

/**
 * Erzeugt das DATEV-Paket für einen Zeitraum und protokolliert den Export.
 * Bei $trigger = ManualEmail/AutoEmail wird das Paket zusätzlich an den
 * Steuerberater (DatevSettings::$advisorEmail) geschickt. Der Handler gibt
 * das DatevExportPackage zurück (z. B. für den Download).
 */
final class CreateDatevExport implements CommandInterface
{
    public function __construct(
        public readonly DateTimeImmutable $from,
        public readonly DateTimeImmutable $to,
        public readonly DatevExportTrigger $trigger,
    ) {
    }
}
