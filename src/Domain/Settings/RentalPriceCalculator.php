<?php

declare(strict_types=1);

namespace App\Domain\Settings;

/**
 * Berechnet aus dem Preis pro Einsatztag eines Artikels (Article::$dailyRentalPrice)
 * und der Preisstaffelung (PriceTier) den Mietpreis für die konfigurierte
 * Anzahl Einsatztage (siehe PriceTier::MAX_DAY_COUNT).
 */
final class RentalPriceCalculator
{
    /**
     * @param PriceTier[] $tiers
     * @return array<int, string> Einsatztage => Mietpreis in Euro, als String mit 2 Nachkommastellen
     */
    public static function calculate(string $dailyRentalPrice, array $tiers): array
    {
        $prices = [];

        foreach ($tiers as $tier) {
            $price = ((float) $dailyRentalPrice) * ((float) $tier->percentage) / 100;
            $prices[$tier->dayCount] = number_format($price, 2, '.', '');
        }

        ksort($prices);

        return $prices;
    }
}
