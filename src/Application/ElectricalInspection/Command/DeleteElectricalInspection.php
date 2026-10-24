<?php

declare(strict_types=1);

namespace App\Application\ElectricalInspection\Command;

use App\Application\Command\CommandInterface;

final class DeleteElectricalInspection implements CommandInterface
{
    public function __construct(public readonly string $id)
    {
    }
}
