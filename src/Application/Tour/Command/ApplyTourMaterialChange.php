<?php

declare(strict_types=1);

namespace App\Application\Tour\Command;

use App\Application\Command\CommandInterface;

/**
 * Bulk change of the material at several stops: $quantity is the number of
 * pieces needed at each selected stop (the deviation from the base list is
 * derived from it; equal to the base list removes the deviation).
 */
final class ApplyTourMaterialChange implements CommandInterface
{
    /** @param string[] $stopIds */
    public function __construct(
        public readonly string $rentalId,
        public readonly array $stopIds,
        public readonly string $articleId,
        public readonly string $quantity,
        public readonly string $allocation,
        public readonly string $issueWaypointId = '',
        public readonly string $returnWaypointId = '',
    ) {
    }
}
