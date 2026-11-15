<?php

declare(strict_types=1);

namespace App\Application\Network\Command;

use App\Application\Command\CommandInterface;

/** Assign a category a fixed address range within a network (migration 155). */
final class SaveNetworkCategoryRange implements CommandInterface
{
    public function __construct(
        public readonly string $networkId,
        public readonly string $categoryId,
        public readonly ?string $ipv4Start = null,
        public readonly ?string $ipv4End = null,
        public readonly ?string $ipv6Start = null,
        public readonly ?string $ipv6End = null,
    ) {
    }
}
