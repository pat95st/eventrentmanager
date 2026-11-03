<?php

declare(strict_types=1);

namespace App\Application\InvoiceCorrection\Command;

use App\Application\Command\CommandInterface;

/** Ausgestellte Rechnungskorrektur als PDF per E-Mail verschicken (Migration 122). */
final class EmailInvoiceCorrection implements CommandInterface
{
    public function __construct(
        public readonly string $id,
        public readonly string $recipientEmail,
        public readonly ?string $note = null,
    ) {
    }
}
