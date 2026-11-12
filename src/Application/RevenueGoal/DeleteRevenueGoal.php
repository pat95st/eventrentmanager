<?php

declare(strict_types=1);

namespace App\Application\RevenueGoal;

use App\Application\Command\CommandInterface;

final class DeleteRevenueGoal implements CommandInterface
{
    public function __construct(public readonly string $id)
    {
    }
}
