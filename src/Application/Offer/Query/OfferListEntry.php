<?php

declare(strict_types=1);

namespace App\Application\Offer\Query;

use App\Domain\Customer\Customer;
use App\Domain\Offer\Offer;

final class OfferListEntry
{
    public function __construct(
        public readonly Offer $offer,
        public readonly ?Customer $customer,
    ) {
    }
}
