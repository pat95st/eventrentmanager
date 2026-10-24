<?php

declare(strict_types=1);

namespace App\Application\Category\Command;

use App\Application\Command\CommandInterface;

final class DeleteCategory implements CommandInterface
{
    public function __construct(public readonly string $id)
    {
    }
}
