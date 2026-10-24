<?php

declare(strict_types=1);

namespace App\Domain\Settings;

enum SmtpEncryption: string
{
    case None = 'none';
    case Ssl = 'ssl';
    case Tls = 'tls';

    public function label(): string
    {
        return match ($this) {
            self::None => 'Keine',
            self::Ssl => 'SSL/TLS (implizit, meist Port 465)',
            self::Tls => 'STARTTLS (meist Port 587)',
        };
    }
}
