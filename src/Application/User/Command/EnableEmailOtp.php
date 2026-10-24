<?php

declare(strict_types=1);

namespace App\Application\User\Command;

use App\Application\Command\CommandInterface;

final class EnableEmailOtp implements CommandInterface
{
    public function __construct(public readonly string $userId)
    {
    }
}
