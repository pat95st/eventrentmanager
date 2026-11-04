<?php

declare(strict_types=1);

namespace App\Application\Banking\Command;

use App\Application\Command\CommandInterface;

final class DeleteAccountTransaction implements CommandInterface
{
    public function __construct(public readonly string $id)
    {
    }
}
