<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail;

use DateTimeImmutable;

/**
 * Eine vom IMAP-/POP3-Client abgerufene und lokal geparste Nachricht,
 * protokollunabhängiges DTO (siehe MailboxClientInterface).
 */
final class MailboxMessage
{
    /** @param MailboxAttachment[] $attachments */
    public function __construct(
        public readonly string $messageId,
        public readonly string $fromAddress,
        public readonly ?string $subject,
        public readonly ?DateTimeImmutable $date,
        public readonly array $attachments,
    ) {
    }
}
