<?php

declare(strict_types=1);

namespace App\Application\RentalSeries\Command;

use App\Application\Command\CommandInterface;

/** Turns an occurrence back into an ordinary rental. */
final class DetachRentalFromSeries implements CommandInterface
{
    public function __construct(public readonly string $rentalId)
    {
    }
}
