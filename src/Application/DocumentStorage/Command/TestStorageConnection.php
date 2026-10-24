<?php

declare(strict_types=1);

namespace App\Application\DocumentStorage\Command;

use App\Application\Command\CommandInterface;

final class TestStorageConnection implements CommandInterface
{
    public function __construct(public readonly string $id)
    {
    }
}
