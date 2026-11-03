<?php

declare(strict_types=1);

namespace App\Application\Settings\Command;

use App\Application\Command\CommandInterface;

final class UpdatePdfFontAssignments implements CommandInterface
{
    /** @param array<string, string|null> $assignments Bereich => Schrift-ID ('' / null = Standard) */
    public function __construct(public readonly array $assignments)
    {
    }
}
