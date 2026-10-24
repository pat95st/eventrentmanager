<?php

declare(strict_types=1);

namespace App\Application\ReceiptInbox\Command;

use App\Application\Command\CommandInterface;

final class AddAllowedSender implements CommandInterface
{
    public function __construct(
        public readonly string $email,
        public readonly string $note,
    ) {
    }
}
