<?php

declare(strict_types=1);

namespace App\Application\PowerPlan\Command;

use App\Application\Command\CommandInterface;

/** Input power of a device in watts (migration 157); cables, bundles and power distributors have none. */
final class SetArticleInputPower implements CommandInterface
{
    public function __construct(
        public readonly string $articleId,
        public readonly ?string $watts,
    ) {
    }
}
