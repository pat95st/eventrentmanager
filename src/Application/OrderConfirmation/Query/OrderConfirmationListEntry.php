<?php

declare(strict_types=1);

namespace App\Application\OrderConfirmation\Query;

use App\Domain\Customer\Customer;
use App\Domain\OrderConfirmation\OrderConfirmation;

final class OrderConfirmationListEntry
{
    public function __construct(
        public readonly OrderConfirmation $orderConfirmation,
        public readonly ?Customer $customer,
    ) {
    }
}
