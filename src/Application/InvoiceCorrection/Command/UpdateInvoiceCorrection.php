<?php

declare(strict_types=1);

namespace App\Application\InvoiceCorrection\Command;

use App\Application\Command\CommandInterface;

/** Ändert einen Entwurf (bei Stornorechnungen nur Grund und Hinweis). */
final class UpdateInvoiceCorrection implements CommandInterface
{
    public function __construct(
        public readonly string $id,
        public readonly InvoiceCorrectionInput $input,
    ) {
    }
}
