<?php

declare(strict_types=1);

namespace App\Application\Routing\Command;

use App\Application\Command\CommandInterface;

/** Whether a tour bills travel costs and how they appear on documents (migration 182). */
final class SetTourTravelCosts implements CommandInterface
{
    public function __construct(public readonly string $rentalId, public readonly bool $enabled, public readonly string $mode)
    {
    }
}
