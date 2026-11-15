<?php

declare(strict_types=1);

namespace App\Application\Federation\Query;

use App\Application\Query\QueryInterface;

/** One partner with its requests. */
final class GetPartner implements QueryInterface
{
    public function __construct(
        public readonly string $partnerId,
    ) {
    }
}
