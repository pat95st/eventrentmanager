<?php

declare(strict_types=1);

namespace App\Application\Routing\Command;

use App\Application\Command\CommandInterface;

final class SetRouteProfile implements CommandInterface
{
    public function __construct(public readonly string $rentalId, public readonly string $profile)
    {
    }
}
