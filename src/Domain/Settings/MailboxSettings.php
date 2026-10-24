<?php

declare(strict_types=1);

namespace App\Domain\Settings;

final class MailboxSettings
{
    public function __construct(
        public MailboxProtocol $protocol,
        public ?string $host,
        public ?int $port,
        public MailboxEncryption $encryption,
        public ?string $username,
        public ?string $password,
        // Nur für protocol = Imap relevant, bei Pop3 ignoriert (POP3 kennt
        // keine Ordnerstruktur) — siehe ImportReceiptEmailsHandler.
        public string $imapFolder,
        public bool $enabled,
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->host !== null && trim($this->host) !== '' && $this->port !== null;
    }
}
