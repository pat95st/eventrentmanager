<?php

declare(strict_types=1);

namespace App\Application\Network;

use App\Domain\Network\Network;
use App\Domain\Network\NetworkCategoryRange;

/** Network part of the inventory number form (migration 155). */
final class NetworkInterfaceForm
{
    /**
     * @param NetworkInterfaceFormRow[] $rows
     * @param Network[] $networks
     * @param array<string, array{4: string[], 6: string[]}> $candidates network ID => free addresses per IP version
     */
    public function __construct(
        public readonly array $rows,
        public readonly array $networks,
        public readonly array $candidates,
        public readonly bool $requiresDhcp,
        public readonly ?NetworkCategoryRange $categoryRange,
    ) {
    }

    public function isEmpty(): bool
    {
        return $this->rows === [];
    }

    public function network(?string $id): ?Network
    {
        foreach ($this->networks as $network) {
            if ($network->id === $id) {
                return $network;
            }
        }

        return null;
    }
}
