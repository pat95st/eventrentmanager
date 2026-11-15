<?php

declare(strict_types=1);

namespace App\Application\Federation\Query;

use App\Application\Query\QueryInterface;

/** One request including availability (incoming material) and local technicians. */
final class GetPartnerRequest implements QueryInterface
{
    public function __construct(
        public readonly string $requestId,
    ) {
    }
}
