<?php

declare(strict_types=1);

namespace App\Domain\Receipt;

/** Grobe Kategorisierung eines Belegs für Filterung/Übersicht — bewusst wenige, feste Werte statt Freitext. */
enum ReceiptCategory: string
{
    case Wareneinkauf = 'wareneinkauf';
    case Werkzeug = 'werkzeug';
    case Fahrzeug = 'fahrzeug';
    case Sonstiges = 'sonstiges';

    public function label(): string
    {
        return match ($this) {
            self::Wareneinkauf => 'Wareneinkauf',
            self::Werkzeug => 'Werkzeug',
            self::Fahrzeug => 'Fahrzeug',
            self::Sonstiges => 'Sonstiges',
        };
    }
}
