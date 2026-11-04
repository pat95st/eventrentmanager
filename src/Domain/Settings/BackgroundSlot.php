<?php

declare(strict_types=1);

namespace App\Domain\Settings;

/** Wo ein Hintergrund (Bild oder Video) angezeigt wird (Migration 126). */
enum BackgroundSlot: string
{
    /** Gesamte Oberfläche nach der Anmeldung. */
    case App = 'oberflaeche';
    /** Anmeldeseite inkl. Zwei-Faktor-Abfrage und Passwort-Seiten. */
    case Login = 'anmeldung';

    public function label(): string
    {
        return match ($this) {
            self::App => 'Hintergrund der Oberfläche',
            self::Login => 'Hintergrund der Anmeldeseite',
        };
    }

    /** Öffentliche URL (ohne Versionsparameter), über die die Datei ausgeliefert wird. */
    public function publicPath(): string
    {
        return match ($this) {
            self::App => '/firmenhintergrund',
            self::Login => '/firmenhintergrund-anmeldung',
        };
    }
}
