<?php

declare(strict_types=1);

namespace App\Domain\Messaging;

/**
 * Einer der drei unterstützten Messenger-Dienste für Kunden-Benachrichtigungen
 * und -Erinnerungen (siehe MessagingSettings/MessageTemplate). E-Mail bleibt
 * bewusst außen vor — das ist weiterhin App\Domain\Mail, unverändert.
 */
enum MessagingChannel: string
{
    case WhatsApp = 'whatsapp';
    case Telegram = 'telegram';
    case Signal = 'signal';

    public function label(): string
    {
        return match ($this) {
            self::WhatsApp => 'WhatsApp Business',
            self::Telegram => 'Telegram',
            self::Signal => 'Signal',
        };
    }
}
