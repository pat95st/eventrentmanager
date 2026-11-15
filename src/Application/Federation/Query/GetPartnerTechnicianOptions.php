<?php

declare(strict_types=1);

namespace App\Application\Federation\Query;

use App\Application\Query\QueryInterface;

/** Technicians and trades a partner offers. */
final class GetPartnerTechnicianOptions implements QueryInterface
{
    public function __construct(
        public readonly string $partnerId,
    ) {
    }
}
