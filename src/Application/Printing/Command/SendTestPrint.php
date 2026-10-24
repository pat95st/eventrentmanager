<?php

declare(strict_types=1);

namespace App\Application\Printing\Command;

use App\Application\Command\CommandInterface;

final class SendTestPrint implements CommandInterface
{
    public function __construct(public readonly string $printerId)
    {
    }
}
