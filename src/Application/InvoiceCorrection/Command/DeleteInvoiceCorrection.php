<?php

declare(strict_types=1);

namespace App\Application\InvoiceCorrection\Command;

use App\Application\Command\CommandInterface;

/** Löscht einen Entwurf. */
final class DeleteInvoiceCorrection implements CommandInterface
{
    public function __construct(public readonly string $id)
    {
    }
}
