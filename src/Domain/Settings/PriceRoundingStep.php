<?php

declare(strict_types=1);

namespace App\Domain\Settings;

/**
 * Rundungsschritt für die aus der Preisstaffelung berechneten Mietpreise
 * (siehe RentalPriceCalculator::calculate()) — global konfigurierbar über
 * PricingSettings, damit z. B. ein Tagesmietsatz von 37,50 € mit 15 %
 * Staffel-Prozentsatz (ergäbe 5,625 €) nicht als "krummer" Betrag erscheint,
 * sondern z. B. auf 5,50 € oder 6,00 € gerundet wird.
 *
 * Bewusst eine FESTE Auswahl an Rundungsschritten statt eines frei
 * eingebbaren Werts (siehe Entscheidung in der Aufgabenstellung) — das
 * vermeidet unsinnige/sehr kleine Rundungsschritte und lässt sich im
 * Formular als einfaches Dropdown abbilden.
 */
enum PriceRoundingStep: string
{
    case None = 'none';
    case Cent = '0.01';
    case FiveCent = '0.05';
    case TenCent = '0.10';
    case FiftyCent = '0.50';
    case OneEuro = '1.00';
    case FiveEuro = '5.00';

    public function label(): string
    {
        return match ($this) {
            self::None => 'Keine Rundung',
            self::Cent => 'Auf 1 Cent',
            self::FiveCent => 'Auf 5 Cent',
            self::TenCent => 'Auf 10 Cent',
            self::FiftyCent => 'Auf 50 Cent',
            self::OneEuro => 'Auf 1 Euro',
            self::FiveEuro => 'Auf 5 Euro',
        };
    }

    /** Rundungsschritt in Euro als Fließkommazahl, oder null bei None (= keine Rundung). */
    public function amount(): ?float
    {
        return $this === self::None ? null : (float) $this->value;
    }
}
