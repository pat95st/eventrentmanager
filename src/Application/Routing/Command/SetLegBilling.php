<?php

declare(strict_types=1);

namespace App\Application\Routing\Command;

use App\Application\Command\CommandInterface;

/** Marks the drive to a tour stop as billed or not billed (migration 179). */
final class SetLegBilling implements CommandInterface
{
    public function __construct(public readonly string $stopId, public readonly bool $billed)
    {
    }
}
