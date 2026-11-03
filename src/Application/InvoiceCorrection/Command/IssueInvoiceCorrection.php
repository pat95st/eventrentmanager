<?php

declare(strict_types=1);

namespace App\Application\InvoiceCorrection\Command;

use App\Application\Command\CommandInterface;

/** Stellt einen Entwurf aus: Nummer (RK-0001 …) und Datum werden vergeben, danach unveränderlich. */
final class IssueInvoiceCorrection implements CommandInterface
{
    public function __construct(public readonly string $id)
    {
    }
}
