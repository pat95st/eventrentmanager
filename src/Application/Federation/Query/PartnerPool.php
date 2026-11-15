<?php

declare(strict_types=1);

namespace App\Application\Federation\Query;

use App\Domain\Federation\PartnerInstance;

/** A page of a partner's material pool as delivered by the partner. */
final class PartnerPool
{
    /**
     * @param list<array{id: string, name: string, manufacturer: ?string, category: ?string, daily_price: ?float, discounted_daily_price: ?float, total: int, available: int}> $items
     */
    public function __construct(
        public readonly PartnerInstance $partner,
        public readonly array $items,
        public readonly int $total,
        public readonly int $page,
        public readonly int $pageSize,
        public readonly string $from,
        public readonly string $to,
        public readonly float $discountPercent,
        public readonly ?string $error,
    ) {
    }

    public function totalPages(): int
    {
        return max(1, (int) ceil($this->total / max(1, $this->pageSize)));
    }
}
