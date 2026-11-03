<?php

declare(strict_types=1);

namespace App\Application\PowerDistributor\Command;

use App\Application\Command\CommandInterface;

final class RemovePowerDistributor implements CommandInterface
{
    public function __construct(public readonly string $articleId)
    {
    }
}
