<?php

declare(strict_types=1);

namespace App\Domain\Settings;

/**
 * Eigenständig von SmtpEncryption (dort none/ssl/tls, hier bewusst
 * "starttls" statt "tls" benannt, da IMAP/POP3-STARTTLS im Gegensatz zum
 * SMTP-Pendant kein eigenes Kommando namens "TLS" kennt) — beide Enums
 * unabhängig zu halten vermeidet außerdem, dass eine künftige Änderung an
 * einem Protokoll versehentlich das andere mitbetrifft.
 */
enum MailboxEncryption: string
{
    case None = 'none';
    case Ssl = 'ssl';
    case Starttls = 'starttls';

    public function label(): string
    {
        return match ($this) {
            self::None => 'Keine',
            self::Ssl => 'SSL/TLS (implizit, meist Port 993/995)',
            self::Starttls => 'STARTTLS (meist Port 143/110)',
        };
    }
}
