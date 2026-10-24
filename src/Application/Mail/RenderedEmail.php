<?php

declare(strict_types=1);

namespace App\Application\Mail;

use App\Infrastructure\Mail\MailAttachment;

final class RenderedEmail
{
    public function __construct(
        public readonly string $subject,
        public readonly string $htmlBody,
        public readonly string $textBody,
        // Gesetzt, wenn $htmlBody eine "cid:company-logo"-Referenz enthält
        // (siehe EmailTemplateRenderer::buildLogoAttachment()) UND ein
        // einbettbares (nicht-SVG) Firmenlogo hinterlegt ist — vom Aufrufer
        // zusätzlich zu den eigentlichen Anhängen (PDF o. ä.) an
        // MailerInterface::send() zu übergeben, damit SmtpMailer es inline
        // einbettet statt als Downloadanhang zu verschicken.
        public readonly ?MailAttachment $logoAttachment = null,
    ) {
    }
}
