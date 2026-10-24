<?php

declare(strict_types=1);

namespace App\Application\Printing\Command;

use App\Application\Command\CommandInterface;

final class DeleteLabelPrinter implements CommandInterface
{
    public function __construct(public readonly string $id)
    {
    }
}
