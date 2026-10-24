<?php

declare(strict_types=1);

namespace App\Infrastructure\Messaging;

use App\Domain\Messaging\MessagingChannel;

/**
 * Port für den tatsächlichen Versand über einen der drei Messenger. Bewusst
 * EIN Interface mit drei kanalspezifischen Methoden (statt einer generischen
 * send()-Methode) — Telegram/Signal verschicken Klartext, WhatsApp verlangt
 * dagegen zwingend eine bei Meta genehmigte Vorlage mit positionsbasierten
 * Parametern (siehe MessageTemplateKey-Klassenkommentar); eine gemeinsame
 * Methode würde diesen Unterschied nur verschleiern.
 *
 * @throws MessageSenderException wenn der Versand fehlschlägt — der Aufrufer
 *         entscheidet, wie er das dem Benutzer meldet.
 */
interface MessageSenderInterface
{
    public function sendTelegramMessage(string $chatId, string $text): void;

    public function sendSignalMessage(string $phoneNumber, string $text): void;

    /** @param string[] $parameters Positionsbasiert, ersetzt {{1}}, {{2}}, ... in der bei Meta genehmigten Vorlage. */
    public function sendWhatsappTemplateMessage(string $phoneNumber, string $templateName, string $languageCode, array $parameters): void;

    /** Prüft die hinterlegten Zugangsdaten mit einem möglichst "billigen" API-Aufruf, ohne eine echte Nachricht zu verschicken. @return string Kurze Erfolgsmeldung (z. B. Bot-Benutzername/Telefonnummer) zur Anzeige im Admin-Bereich. */
    public function testConnection(MessagingChannel $channel): string;
}
