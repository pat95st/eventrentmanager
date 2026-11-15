<?php

declare(strict_types=1);

namespace App\Application\Network\Command;

use App\Application\Command\CommandInterface;

final class DeleteNetwork implements CommandInterface
{
    public function __construct(public readonly string $id)
    {
    }
}
