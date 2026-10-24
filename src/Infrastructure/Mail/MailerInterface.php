<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail;

use App\Domain\Mail\MailCategory;

interface MailerInterface
{
    /**
     * @param MailAttachment[] $attachments Optionale Dateianhänge (z. B. PDF-Export).
     *        Leer = einfache Text-Mail wie bisher.
     * @param string|null $htmlBody Optionaler HTML-Inhalt (siehe
     *        App\Application\Mail\EmailTemplateRenderer). Ist er gesetzt,
     *        wird die Nachricht als multipart/alternative (Text + HTML)
     *        verschickt, $textBody bleibt dabei der Klartext-Fallback für
     *        Mail-Clients ohne HTML-Darstellung. null = reine Text-Mail
     *        wie bisher.
     * @param MailCategory|null $category Steuert zusammen mit
     *        SmtpSettings::$archiveCategories, ob diese Mail bei gesetzter
     *        Archivierungsadresse per BCC archiviert wird (siehe
     *        SmtpSettings::shouldArchive()). null = Kategorie unbekannt,
     *        Mail wird sicherheitshalber wie bisher immer archiviert.
     * @throws MailerException wenn der Versand fehlschlägt (falsche
     *         Zugangsdaten, Verbindung abgelehnt, …) — der Aufrufer
     *         entscheidet, wie er das dem Benutzer meldet.
     */
    public function send(string $toAddress, string $subject, string $textBody, array $attachments = [], ?string $htmlBody = null, ?MailCategory $category = null): void;
}
