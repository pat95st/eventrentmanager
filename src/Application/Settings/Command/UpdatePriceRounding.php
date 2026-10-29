<?php

declare(strict_types=1);

namespace App\Application\Settings\Command;

use App\Application\Command\CommandInterface;

/**
 * Speichert nur die Preisrundung (Schritt + Richtung) — Nutzerwunsch: die
 * Rundung wird direkt auf der Seite "Preisstaffelung" gepflegt, weil sie sich
 * unmittelbar auf die dort definierten Staffelpreise auswirkt. Die übrigen
 * Preis-Einstellungen (Zubehörpauschale) bleiben unverändert.
 */
final class UpdatePriceRounding implements CommandInterface
{
    public function __construct(
        public readonly string $priceRoundingStep,
        public readonly string $priceRoundingDirection,
    ) {
    }
}
