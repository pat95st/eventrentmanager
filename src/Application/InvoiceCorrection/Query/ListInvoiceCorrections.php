<?php

declare(strict_types=1);

namespace App\Application\InvoiceCorrection\Query;

use App\Application\Query\QueryInterface;

/** Alle Korrekturen (Übersicht) oder nur die einer Rechnung. */
final class ListInvoiceCorrections implements QueryInterface
{
    public function __construct(
        public readonly ?string $search = null,
        public readonly ?string $invoiceId = null,
    ) {
    }
}
