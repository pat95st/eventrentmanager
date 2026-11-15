<?php

declare(strict_types=1);

namespace App\Application\Federation\Query;

use App\Domain\Federation\PartnerInstance;
use App\Domain\Federation\PartnerRequest;

final class PartnerDetail
{
    /** @param PartnerRequest[] $requests */
    public function __construct(
        public readonly PartnerInstance $partner,
        public readonly array $requests,
        public readonly ?string $customerLabel,
    ) {
    }
}
