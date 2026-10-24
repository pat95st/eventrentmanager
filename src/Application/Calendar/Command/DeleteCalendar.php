<?php

declare(strict_types=1);

namespace App\Application\Calendar\Command;

use App\Application\Command\CommandInterface;

final class DeleteCalendar implements CommandInterface
{
    public function __construct(public readonly string $id)
    {
    }
}
