<?php

declare(strict_types=1);

namespace App\Application\Invoice\Command;

use App\Application\Command\CommandInterface;

final class MarkInvoicePaid implements CommandInterface
{
    public function __construct(public readonly string $id)
    {
    }
}
