<?php

declare(strict_types=1);

namespace App\Application\Warehouse\Command;

use App\Application\Command\CommandInterface;

final class DeleteWarehouse implements CommandInterface
{
    public function __construct(public readonly string $id)
    {
    }
}
