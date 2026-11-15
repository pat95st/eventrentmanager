<?php

declare(strict_types=1);

namespace App\Application\Network;

/**
 * An interface of an article that gets an address per inventory number
 * (migration 155): the article's own network interface ('main') or an
 * interface using an IP-based digital protocol (stagebox, mixing console,
 * expansion card). Assignments whose interface no longer exists are kept
 * as 'orphan' slots until the inventory number is saved again.
 */
final class NetworkInterfaceSlot
{
    public const KIND_MAIN = 'main';
    public const KIND_PROTOCOL = 'protocol';
    public const KIND_ORPHAN = 'orphan';

    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $kind,
        // Expansion cards may not be installed in every unit, so their address is clearly optional.
        public readonly bool $optional = false,
    ) {
    }
}
