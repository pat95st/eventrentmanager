<?php

declare(strict_types=1);

namespace App\Application\Settings\Command;

use App\Application\Command\CommandInterface;

/** Schriftarten je Bereich der Oberfläche speichern (Bereich => Schrift-ID oder leer). */
final class UpdateUiFontAssignments implements CommandInterface
{
    /** @param array<string, ?string> $assignments */
    public function __construct(public readonly array $assignments)
    {
    }
}
