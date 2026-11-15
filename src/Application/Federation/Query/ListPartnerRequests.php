<?php

declare(strict_types=1);

namespace App\Application\Federation\Query;

use App\Application\Query\QueryInterface;

/** Recent requests in both directions. */
final class ListPartnerRequests implements QueryInterface
{
    public function __construct(
        public readonly int $limit = 100,
    ) {
    }
}
