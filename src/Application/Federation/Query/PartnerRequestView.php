<?php

declare(strict_types=1);

namespace App\Application\Federation\Query;

use App\Domain\Federation\PartnerRequest;

final class PartnerRequestView
{
    public function __construct(
        public readonly PartnerRequest $request,
        public readonly string $partnerName,
    ) {
    }
}
