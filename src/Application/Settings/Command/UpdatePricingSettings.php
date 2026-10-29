<?php

declare(strict_types=1);

namespace App\Application\Settings\Command;

use App\Application\Command\CommandInterface;

final class UpdatePricingSettings implements CommandInterface
{
    public function __construct(
        public readonly string $accessoryFlatRatePercent,
        // Optional: die Rundung wird seit dem Nutzerwunsch "Rundung direkt
        // bei der Preisstaffelung" über UpdatePriceRounding gepflegt; null
        // = bestehende Rundungseinstellung unverändert übernehmen.
        public readonly ?string $priceRoundingStep = null,
        public readonly ?string $priceRoundingDirection = null,
    ) {
    }
}
