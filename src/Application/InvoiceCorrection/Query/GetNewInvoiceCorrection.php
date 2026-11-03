<?php

declare(strict_types=1);

namespace App\Application\InvoiceCorrection\Query;

use App\Application\Query\QueryInterface;

/** Daten für das Formular einer neuen Korrektur zur Rechnung. */
final class GetNewInvoiceCorrection implements QueryInterface
{
    public function __construct(public readonly string $invoiceId)
    {
    }
}
