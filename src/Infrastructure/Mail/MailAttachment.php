<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail;

/**
 * Ein einzelner Dateianhang für eine ausgehende E-Mail (siehe
 * MailerInterface::send()). Bewusst generisch gehalten (kein
 * "PackingListAttachment" o. ä.), damit künftige Mail-Versände (z. B. andere
 * PDF-Exporte) dieselbe Anhang-Mechanik im SmtpMailer wiederverwenden können.
 *
 * $contentId/$inline steuern, ob dieser Anhang als "inline"-Bild im HTML-Body
 * per "cid:"-Referenz eingebettet wird (z. B. das Firmenlogo, siehe
 * EmailTemplateRenderer::buildLogoAttachment()), statt als gewöhnlicher
 * Datei-Download im Postfach zu erscheinen — siehe SmtpMailer für den dafür
 * nötigen multipart/related-Aufbau. $contentId ist die ID OHNE spitze
 * Klammern (die fügt SmtpMailer beim Erzeugen des Content-ID-Headers selbst
 * hinzu); ein normaler (nicht-inline) Anhang lässt beides auf dem
 * Standardwert (null/false).
 */
final class MailAttachment
{
    public function __construct(
        public readonly string $filename,
        public readonly string $mimeType,
        public readonly string $content, // Rohe Binärdaten, nicht base64-kodiert
        public readonly ?string $contentId = null,
        public readonly bool $inline = false,
    ) {
    }
}
