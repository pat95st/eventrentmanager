<?php

declare(strict_types=1);

namespace App\Application\OnlinePayment\Query;

use App\Application\OnlinePayment\PayableInvoice;
use App\Domain\OnlinePayment\OnlinePayment;
use App\Domain\OnlinePayment\OnlinePaymentProvider;

final class InvoicePaymentPage
{
    /**
     * @param OnlinePaymentProvider[] $providers usable providers
     */
    public function __construct(
        public readonly PayableInvoice $invoice,
        public readonly array $providers,
        public readonly string $currency,
        public readonly string $companyName,
        public readonly ?OnlinePayment $completedPayment,
    ) {
    }
}
