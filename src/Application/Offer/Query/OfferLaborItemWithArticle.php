<?php

declare(strict_types=1);

namespace App\Application\Offer\Query;

use App\Domain\LaborTime\LaborTimeArticle;
use App\Domain\Offer\OfferLaborItem;
use App\Domain\User\User;

final class OfferLaborItemWithArticle
{
    public function __construct(
        public readonly OfferLaborItem $item,
        public readonly LaborTimeArticle $laborTimeArticle,
        public readonly ?User $user,
    ) {
    }

    public function lineTotal(): float
    {
        return $this->item->lineTotal();
    }
}
