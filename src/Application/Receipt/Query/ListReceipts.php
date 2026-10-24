<?php

declare(strict_types=1);

namespace App\Application\Receipt\Query;

use App\Application\Query\QueryInterface;
use App\Domain\Receipt\ReceiptCategory;
use App\Domain\Receipt\ReceiptStatus;

final class ListReceipts implements QueryInterface
{
    public function __construct(
        public readonly ?string $searchTerm = null, // Lieferant, Beleg-/Rechnungsnummer oder Notiz
        public readonly ?string $supplierId = null,
        public readonly ?ReceiptStatus $status = null,
        public readonly ?ReceiptCategory $category = null,
        public readonly int $page = 1,
        public readonly int $pageSize = 25,
    ) {
    }
}
