<?php

declare(strict_types=1);

namespace App\Domain\Messaging;

final class MessageTemplate
{
    public function __construct(
        public readonly MessagingChannel $channel,
        public readonly MessageTemplateKey $key,
        // Telegram/Signal: frei editierbarer Klartext mit {{platzhalter}}. Bei WhatsApp ungenutzt (bleibt null).
        public ?string $bodyText = null,
        // WhatsApp: Name der bei Meta genehmigten Vorlage. Bei Telegram/Signal ungenutzt (bleibt null).
        public ?string $metaTemplateName = null,
        public string $metaTemplateLanguage = 'de',
    ) {
    }
}
