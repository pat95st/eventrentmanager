<?php

declare(strict_types=1);

namespace App\Application\Network;

/** One interface row in the inventory number form (migration 155). */
final class NetworkInterfaceFormRow
{
    public function __construct(
        public readonly NetworkInterfaceSlot $slot,
        public readonly ?string $networkId,
        public readonly ?string $ipv4,
        public readonly ?string $ipv6,
        public readonly ?string $mac,
        // True if the addresses are suggestions (not yet saved), so the form may replace them when the network changes.
        public readonly bool $suggested,
    ) {
    }
}
