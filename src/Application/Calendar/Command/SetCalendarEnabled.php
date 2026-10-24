<?php

declare(strict_types=1);

namespace App\Application\Calendar\Command;

use App\Application\Command\CommandInterface;

final class SetCalendarEnabled implements CommandInterface
{
    public function __construct(
        public readonly string $id,
        public readonly bool $enabled,
    ) {
    }
}
