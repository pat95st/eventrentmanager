<?php

declare(strict_types=1);

namespace App\Domain\Messaging;

interface MessageTemplateRepositoryInterface
{
    /** Liefert die gespeicherte Vorlage, oder eine mit eingebauten Standardwerten (MessageTemplateKey::defaultBodyText()), falls (noch) keine Zeile existiert. */
    public function get(MessagingChannel $channel, MessageTemplateKey $key): MessageTemplate;

    /** @return MessageTemplate[] Alle für diesen Kanal zutreffenden Vorlagen (siehe MessageTemplateKey::appliesToChannel()), in der Reihenfolge von MessageTemplateKey::cases(). */
    public function getAllForChannel(MessagingChannel $channel): array;

    public function save(MessageTemplate $template): void;

    /** Setzt eine Vorlage auf die eingebauten Standardwerte zurück (löscht die Zeile). */
    public function resetToDefault(MessagingChannel $channel, MessageTemplateKey $key): void;
}
