<?php

declare(strict_types=1);

namespace App\Application\Venue\Command;

use App\Application\Command\CommandInterface;

final class DeleteVenue implements CommandInterface
{
    public function __construct(public readonly string $id)
    {
    }
}
