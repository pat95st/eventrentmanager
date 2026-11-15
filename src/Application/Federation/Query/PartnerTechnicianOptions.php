<?php

declare(strict_types=1);

namespace App\Application\Federation\Query;

use App\Domain\Federation\PartnerInstance;

final class PartnerTechnicianOptions
{
    /**
     * @param list<array{id: string, name: string}> $technicians
     * @param string[] $trades
     */
    public function __construct(
        public readonly PartnerInstance $partner,
        public readonly bool $allowed,
        public readonly array $technicians,
        public readonly array $trades,
        public readonly ?string $error,
    ) {
    }
}
