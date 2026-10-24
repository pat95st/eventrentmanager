<?php

declare(strict_types=1);

namespace App\Application\Rental\Command;

use App\Application\Command\CommandInterface;

final class DeleteRental implements CommandInterface
{
    public function __construct(public readonly string $id)
    {
    }
}
