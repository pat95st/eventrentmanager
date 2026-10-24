<?php

declare(strict_types=1);

namespace App\Application\Purchase\Command;

use App\Application\Command\CommandInterface;

final class MarkPurchaseOrderOrdered implements CommandInterface
{
    public function __construct(public readonly string $id)
    {
    }
}
