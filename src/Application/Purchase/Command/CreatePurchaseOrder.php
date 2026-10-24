<?php

declare(strict_types=1);

namespace App\Application\Purchase\Command;

use App\Application\Command\CommandInterface;

final class CreatePurchaseOrder implements CommandInterface
{
    /**
     * @param array<int, array{article_id: string, target_type: string, quantity: string, unit_price: string}> $items
     */
    public function __construct(
        public readonly string $supplierId,
        public readonly string $note,
        public readonly array $items,
    ) {
    }
}
