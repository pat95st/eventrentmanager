<?php

declare(strict_types=1);

namespace App\Application\Invoice\Query;

use App\Domain\Customer\Customer;
use App\Domain\Invoice\Invoice;

final class InvoiceListEntry
{
    public function __construct(
        public readonly Invoice $invoice,
        public readonly ?Customer $customer,
    ) {
    }
}
