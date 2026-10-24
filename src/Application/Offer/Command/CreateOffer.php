<?php

declare(strict_types=1);

namespace App\Application\Offer\Command;

use App\Application\Command\CommandInterface;

final class CreateOffer implements CommandInterface
{
    /**
     * @param array<int, array{article_id: string, quantity: string, unit_price: string, line_note: string}> $items
     * @param array<int, array{labor_article_id: string, hours: string}> $laborItems
     * @param array<int, array{article_id: string, source: string, inventory_number_id: string, quantity: string, unit_price: string, line_note: string}> $saleItems
     */
    public function __construct(
        public readonly string $customerId,
        public readonly string $startDate, // 'Y-m-d'
        public readonly string $endDate,   // 'Y-m-d'
        public readonly string $eventLocation, // Freitext; wird im Handler ggf. einem gespeicherten Veranstaltungsort zugeordnet
        public readonly string $validUntil, // 'Y-m-d', optional (leer = kein Ablaufdatum)
        public readonly string $note,
        public readonly array $items,
        public readonly bool $accessoryFlatRateEnabled = true,
        public readonly ?string $discountType = null, // 'percent'|'fixed'|null
        public readonly ?string $discountValue = null,
        public readonly array $laborItems = [],
        public readonly array $saleItems = [],
    ) {
    }
}
