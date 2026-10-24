<?php

declare(strict_types=1);

namespace App\Application\OrderConfirmation\Command;

use App\Application\Command\CommandInterface;

final class MarkOrderConfirmationConfirmed implements CommandInterface
{
    public function __construct(public readonly string $id)
    {
    }
}
