<?php

declare(strict_types=1);

namespace App\Application\Federation\Query;

use App\Application\Query\QueryInterface;

/** Browse a partner's material pool for a period. */
final class BrowsePartnerPool implements QueryInterface
{
    public function __construct(
        public readonly string $partnerId,
        public readonly ?string $search,
        public readonly string $from,
        public readonly string $to,
        public readonly int $page = 1,
    ) {
    }
}
