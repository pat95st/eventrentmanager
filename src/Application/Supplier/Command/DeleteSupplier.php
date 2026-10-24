<?php

declare(strict_types=1);

namespace App\Application\Supplier\Command;

use App\Application\Command\CommandInterface;

final class DeleteSupplier implements CommandInterface
{
    public function __construct(public readonly string $id)
    {
    }
}
