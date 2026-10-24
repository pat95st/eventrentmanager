<?php

declare(strict_types=1);

namespace App\Application\ReceiptInbox\Command;

use App\Application\Command\CommandInterface;

final class DeleteAllowedSender implements CommandInterface
{
    public function __construct(public readonly string $id)
    {
    }
}
