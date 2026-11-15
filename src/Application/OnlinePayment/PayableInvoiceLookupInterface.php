<?php

declare(strict_types=1);

namespace App\Application\OnlinePayment;

interface PayableInvoiceLookupInterface
{
    public function find(string $invoiceId): ?PayableInvoice;
}
