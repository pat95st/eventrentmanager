<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail;

/**
 * Protokollunabhängige Schnittstelle, die ImapClient und Pop3Client beide
 * implementieren, damit der Import-Service (siehe
 * App\Application\ReceiptInbox\Command\ImportReceiptEmailsHandler) nicht
 * wissen muss, welches Protokoll konfiguriert ist.
 */
interface MailboxClientInterface
{
    /**
     * Ruft ALLE Nachrichten im konfigurierten Postfach/Ordner ab (nicht nur
     * ungelesene) und parst sie lokal (siehe MimeMessageParser). Das
     * Postfach selbst wird dabei NICHT verändert — kein "gelesen"-Markieren,
     * kein Verschieben/Löschen; Dedup läuft ausschließlich über die
     * messageId in der Anwendungsdatenbank (siehe
     * receipt_inbox_messages.message_id).
     *
     * @return MailboxMessage[]
     * @throws MailboxClientException bei Verbindungs-/Login-/Protokollfehlern.
     */
    public function fetchMessages(): array;
}
