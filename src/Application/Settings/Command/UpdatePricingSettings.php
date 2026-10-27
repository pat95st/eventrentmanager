<?php

declare(strict_types=1);

namespace App\Application\Settings\Command;

use App\Application\Command\CommandInterface;

final class UpdatePricingSettings implements CommandInterface
{
    public function __construct(
        public readonly string $accessoryFlatRatePercent,
        public readonly string $priceRoundingStep,
        public readonly string $priceRoundingDirection,
    ) {
    }
}
