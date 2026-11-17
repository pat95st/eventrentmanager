<?php

declare(strict_types=1);

namespace App\Application\PowerPlan\Command;

use App\Application\Command\CommandInterface;

/**
 * Peak power (migration 159) and average power (migration 173) of a device in
 * watts; cables, bundles and power distributors have none.
 */
final class SetArticleInputPower implements CommandInterface
{
    public function __construct(
        public readonly string $articleId,
        public readonly ?string $watts,
        public readonly ?string $averageWatts = null,
    ) {
    }
}
