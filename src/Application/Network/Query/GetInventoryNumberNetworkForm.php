<?php

declare(strict_types=1);

namespace App\Application\Network\Query;

use App\Application\Query\QueryInterface;

/** Network part of the inventory number form; inventoryNumberId null for a new one. Returns NetworkInterfaceForm. */
final class GetInventoryNumberNetworkForm implements QueryInterface
{
    public function __construct(
        public readonly string $articleId,
        public readonly ?string $inventoryNumberId = null,
    ) {
    }
}
