<?php

declare(strict_types=1);

namespace App\Application\Settings\Command;

use App\Application\Command\CommandInterface;

final class UpdateMailboxSettings implements CommandInterface
{
    /** @param string|null $password null = Passwort unverändert lassen (Formular zeigt das gespeicherte Passwort nie im Klartext an, siehe UpdateSmtpSettings). */
    public function __construct(
        public readonly string $protocol,
        public readonly string $host,
        public readonly string $port,
        public readonly string $encryption,
        public readonly ?string $username,
        public readonly ?string $password,
        public readonly string $imapFolder,
        public readonly bool $enabled,
    ) {
    }
}
