<?php

declare(strict_types=1);

namespace App\Application\Federation\Query;

use App\Domain\Federation\PartnerInstance;

final class RentalPartnerRequests
{
    /**
     * @param PartnerRequestView[] $requests
     * @param PartnerInstance[] $activePartners
     */
    public function __construct(
        public readonly array $requests,
        public readonly array $activePartners,
    ) {
    }
}
