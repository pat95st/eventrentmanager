<?php

declare(strict_types=1);

namespace App\Application\Tour\Command;

use App\Application\Command\CommandInterface;

/** Removes one material deviation of a stop. */
final class DeleteTourStopItem implements CommandInterface
{
    public function __construct(public readonly string $rentalId, public readonly string $itemId)
    {
    }
}
