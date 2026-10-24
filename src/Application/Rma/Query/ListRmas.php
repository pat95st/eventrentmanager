<?php

declare(strict_types=1);

namespace App\Application\Rma\Query;

use App\Application\Query\QueryInterface;
use App\Domain\Rma\RmaStatus;

final class ListRmas implements QueryInterface
{
    public function __construct(
        public readonly ?RmaStatus $status = null,
        public readonly ?string $searchTerm = null, // durchsucht Artikelname, Hersteller, Inventurnummer
    ) {
    }
}
