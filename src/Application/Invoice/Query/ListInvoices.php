<?php

declare(strict_types=1);

namespace App\Application\Invoice\Query;

use App\Application\Query\QueryInterface;
use App\Domain\Invoice\InvoiceStatus;

final class ListInvoices implements QueryInterface
{
    public function __construct(
        public readonly ?string $searchTerm = null, // Kundenname, Ort oder Rechnungsnummer
        public readonly ?InvoiceStatus $status = null,
        public readonly int $page = 1,
        public readonly int $pageSize = 25,
    ) {
    }
}
