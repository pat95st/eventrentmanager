<?php

declare(strict_types=1);

namespace App\Application\Offer\Query;

use App\Application\Query\QueryInterface;
use App\Domain\Offer\OfferStatus;

final class ListOffers implements QueryInterface
{
    public function __construct(
        public readonly ?string $searchTerm = null, // Kundenname oder Veranstaltungsort
        public readonly ?OfferStatus $status = null,
        public readonly int $page = 1,
        public readonly int $pageSize = 25,
    ) {
    }
}
