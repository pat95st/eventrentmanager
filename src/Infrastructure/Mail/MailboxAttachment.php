<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail;

/**
 * Ein aus einer eingehenden E-Mail extrahierter Anhang (siehe
 * MimeMessageParser) — bereits auf die erlaubten MIME-Types
 * (application/pdf, image/jpeg, image/png) gefiltert.
 */
final class MailboxAttachment
{
    public function __construct(
        public readonly string $filename,
        public readonly string $mimeType,
        public readonly string $rawBytes, // Bereits dekodiert (kein Base64/Quoted-Printable mehr).
    ) {
    }
}
