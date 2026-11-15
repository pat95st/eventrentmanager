<?php

declare(strict_types=1);

namespace App\Application\Federation\Query;

use App\Domain\Federation\PartnerInstance;
use App\Domain\Federation\PartnerRequest;

final class PartnerRequestDetail
{
    /**
     * @param array<string, int> $shortages article id => available quantity (incoming material, pending)
     * @param list<array{id: string, name: string}> $technicians local technicians (incoming technician request)
     * @param list<array{id: string, name: string}> $trades local trades
     */
    public function __construct(
        public readonly PartnerRequest $request,
        public readonly PartnerInstance $partner,
        public readonly array $shortages,
        public readonly array $technicians,
        public readonly array $trades,
    ) {
    }
}
