<?php

declare(strict_types=1);

namespace App\Domain\Network;

/**
 * IP and MAC address of one interface of an inventory number (migration
 * 155). interfaceKey identifies the interface (see NetworkInterfaceSlot).
 */
final class NetworkInterfaceAssignment
{
    public function __construct(
        public readonly string $inventoryNumberId,
        public readonly string $interfaceKey,
        public string $label,
        public ?string $networkId = null,
        public ?string $ipv4Address = null,
        public ?string $ipv6Address = null,
        public ?string $macAddress = null,
    ) {
    }

    public function address(int $version): ?string
    {
        return $version === 4 ? $this->ipv4Address : $this->ipv6Address;
    }

    public function isEmpty(): bool
    {
        return $this->networkId === null && $this->ipv4Address === null && $this->ipv6Address === null && $this->macAddress === null;
    }
}
