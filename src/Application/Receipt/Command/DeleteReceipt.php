<?php

declare(strict_types=1);

namespace App\Application\Receipt\Command;

use App\Application\Command\CommandInterface;

final class DeleteReceipt implements CommandInterface
{
    public function __construct(public readonly string $id)
    {
    }
}
