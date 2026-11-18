<?php

declare(strict_types=1);

namespace App\Application\Tour\Command;

use App\Application\Command\CommandInterface;

/** Bulk change of the crew at several stops (replace/add/remove). */
final class ApplyTourCrewChange implements CommandInterface
{
    /** @param string[] $stopIds */
    public function __construct(
        public readonly string $rentalId,
        public readonly array $stopIds,
        public readonly string $action,
        public readonly string $userId,
        public readonly string $replacesUserId = '',
    ) {
    }
}
