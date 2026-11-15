<?php

declare(strict_types=1);

namespace App\Application\Network\Query;

use App\Application\Network\NetworkInterfaceSlot;
use App\Domain\Network\ArticleNetworkSettings;
use App\Domain\Network\Network;
use App\Domain\Network\NetworkInterfaceAssignment;

/** Network data of an article and its inventory numbers (migration 155). */
final class ArticleNetworkOverview
{
    /**
     * @param NetworkInterfaceSlot[] $slots
     * @param array<string, NetworkInterfaceAssignment[]> $assignments inventory number ID => assignments
     * @param array<string, Network> $networks by ID
     */
    public function __construct(
        public readonly ?ArticleNetworkSettings $settings,
        public readonly array $slots,
        public readonly array $assignments,
        public readonly array $networks,
        public readonly bool $isCable,
    ) {
    }

    public function hasInterfaces(): bool
    {
        return $this->slots !== [];
    }

    /** @return NetworkInterfaceAssignment[] */
    public function assignmentsFor(string $inventoryNumberId): array
    {
        return $this->assignments[$inventoryNumberId] ?? [];
    }

    public function network(?string $id): ?Network
    {
        return $id !== null ? ($this->networks[$id] ?? null) : null;
    }
}
