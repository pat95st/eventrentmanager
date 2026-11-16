<?php

declare(strict_types=1);

namespace App\Application\Display\Command;

use App\Application\Command\CommandInterface;

final class RegenerateDisplayClientToken implements CommandInterface
{
    public function __construct(public readonly string $id)
    {
    }
}
