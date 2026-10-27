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
     *
     * $roundingStep/$roundingDirection (siehe PricingSettings) runden JEDEN
     * Staffel-Preis zusätzlich auf den konfigurierten Schritt — bewusst mit
     * Default "keine Rundung", damit Aufrufer, die hier eine reine
     * Prozent-Kennzahl statt eines echten Preises berechnen (z. B. mit
     * dailyRentalPrice = "1", um clientseitig einen Multiplikator zu
     * gewinnen — siehe InvoiceController/OfferController/
     * OrderConfirmationController::renderForm()), diese Parameter einfach
     * weglassen können, ohne das Ergebnis zu verfälschen.
     */
    public static function calculate(
        string $dailyRentalPrice,
        array $tiers,
        PriceRoundingStep $roundingStep = PriceRoundingStep::None,
        PriceRoundingDirection $roundingDirection = PriceRoundingDirection::Nearest,
    ): array {
        $prices = [];

        foreach ($tiers as $tier) {
            $price = ((float) $dailyRentalPrice) * ((float) $tier->percentage) / 100;
            $price = self::round($price, $roundingStep, $roundingDirection);
            $prices[$tier->dayCount] = number_format($price, 2, '.', '');
        }

        ksort($prices);

        return $prices;
    }

    /**
     * Rundet $price auf den durch $step vorgegebenen Schritt (z. B. 0.50 €),
     * in die durch $direction vorgegebene Richtung. $step === None lässt
     * $price unverändert (keine Rundung).
     *
     * Die Division/Multiplikation mit dem Schritt kann durch
     * Fließkomma-Ungenauigkeiten z. B. 0.49999999999 statt 0.5 ergeben —
     * round(..., 6) VOR ceil()/floor() gleicht das aus, ohne die eigentliche
     * Rundungslogik zu verfälschen (6 Nachkommastellen liegen weit unter der
     * hier relevanten Genauigkeit von Centbeträgen).
     */
    private static function round(float $price, PriceRoundingStep $step, PriceRoundingDirection $direction): float
    {
        $stepAmount = $step->amount();
        if ($stepAmount === null || $stepAmount <= 0) {
            return $price;
        }

        $units = round($price / $stepAmount, 6);

        $roundedUnits = match ($direction) {
            PriceRoundingDirection::Up => ceil($units),
            PriceRoundingDirection::Down => floor($units),
            PriceRoundingDirection::Nearest => round($units),
        };

        return round($roundedUnits * $stepAmount, 2);
    }
}
