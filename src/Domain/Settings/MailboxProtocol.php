<?php

declare(strict_types=1);

namespace App\Domain\Settings;

/** Protokoll für den Abruf des Beleg-Posteingangs, siehe MailboxSettings. */
enum MailboxProtocol: string
{
    case Imap = 'imap';
    case Pop3 = 'pop3';

    public function label(): string
    {
        return match ($this) {
            self::Imap => 'IMAP',
            self::Pop3 => 'POP3',
        };
    }
}
