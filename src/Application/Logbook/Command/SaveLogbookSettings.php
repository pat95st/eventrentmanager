<?php

declare(strict_types=1);

namespace App\Application\Logbook\Command;

use App\Application\Command\CommandInterface;

/** Global logbook settings (migration 184). */
final class SaveLogbookSettings implements CommandInterface
{
    public function __construct(public readonly ?string $editWindowDays)
    {
    }
}
