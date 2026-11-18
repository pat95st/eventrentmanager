<?php

declare(strict_types=1);

namespace App\Application\Tour\Command;

use App\Application\Command\CommandInterface;

/** Removes the tour planning again (only while the tour has at most one stop). */
final class RevertTour implements CommandInterface
{
    public function __construct(public readonly string $rentalId)
    {
    }
}
