<?php

declare(strict_types=1);

namespace App\Application\Tour\Command;

use App\Application\Command\CommandInterface;

/** Plans an event as a tour; its location and dates become the first stop. */
final class MakeTour implements CommandInterface
{
    public function __construct(public readonly string $rentalId)
    {
    }
}
