<?php

declare(strict_types=1);

namespace App\Application\Network\Command;

use App\Application\Command\CommandInterface;

/**
 * Create (id null) or update a network (migration 155). Network fields
 * accept CIDR notation ("192.168.10.0/24"); the prefix fields accept "24",
 * "/24" or, for IPv4, a dotted subnet mask ("255.255.255.0").
 */
final class SaveNetwork implements CommandInterface
{
    public function __construct(
        public readonly ?string $id,
        public readonly string $name,
        public readonly string $ipMode,
        public readonly ?string $vlanId = null,
        public readonly ?string $ipv4Network = null,
        public readonly ?string $ipv4Mask = null,
        public readonly ?string $ipv6Network = null,
        public readonly ?string $ipv6Prefix = null,
        public readonly ?string $ipv6DefaultPrefix = null,
        public readonly ?string $notes = null,
        public readonly ?string $sortOrder = null,
    ) {
    }
}
