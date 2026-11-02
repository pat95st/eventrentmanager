<?php

declare(strict_types=1);

namespace App\Application\Rack\Command;

use App\Application\Command\CommandInterface;

final class RemoveRackSlot implements CommandInterface
{
    public function __construct(public readonly string $slotId)
    {
    }
}
