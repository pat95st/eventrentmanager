<?php

declare(strict_types=1);

namespace App\Application\Network\Query;

use App\Domain\Network\Network;

final class NetworkSettings
{
    /**
     * @param Network[] $networks
     * @param array<string, int> $assignmentCounts network ID => assigned interfaces
     * @param array<string, int> $rangeCounts network ID => category ranges
     */
    public function __construct(
        public readonly array $networks,
        public readonly array $assignmentCounts,
        public readonly array $rangeCounts,
    ) {
    }
}
