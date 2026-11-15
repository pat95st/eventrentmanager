<?php

declare(strict_types=1);

namespace App\Domain\Network;

/** One assigned address of a network for the address list (migration 155). */
final class NetworkAddressEntry
{
    public function __construct(
        public readonly NetworkInterfaceAssignment $assignment,
        public readonly string $inventoryNumber,
        public readonly string $articleId,
        public readonly string $articleName,
    ) {
    }
}
