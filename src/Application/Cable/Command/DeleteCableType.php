<?php

declare(strict_types=1);

namespace App\Application\Cable\Command;

use App\Application\Command\CommandInterface;

final class DeleteCableType implements CommandInterface
{
    public function __construct(public readonly string $id)
    {
    }
}
