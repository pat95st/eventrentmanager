<?php

declare(strict_types=1);

namespace App\Application\Invoice\Command;

use App\Application\Command\CommandInterface;

final class EmailInvoicePdf implements CommandInterface
{
    public function __construct(
        public readonly string $invoiceId,
        public readonly string $recipientEmail,
        public readonly ?string $note,
    ) {
    }
}
