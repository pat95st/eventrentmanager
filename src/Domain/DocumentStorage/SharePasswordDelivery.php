<?php

declare(strict_types=1);

namespace App\Domain\DocumentStorage;

/**
 * Auf welchem Weg ein frisch erzeugtes Freigabe-Passwort an den Kunden geht.
 * Der Benutzer entscheidet das jedes Mal selbst — ohne Auswahl wird nichts
 * verschickt. WhatsApp fehlt bewusst: dort sind nur vorab bei Meta genehmigte
 * Vorlagen erlaubt (siehe MessageTemplateKey).
 */
enum SharePasswordDelivery: string
{
    case Email = 'email';
    case Telegram = 'telegram';
    case Signal = 'signal';

    public function label(): string
    {
        return match ($this) {
            self::Email => 'E-Mail',
            self::Telegram => 'Telegram',
            self::Signal => 'Signal',
        };
    }
}
