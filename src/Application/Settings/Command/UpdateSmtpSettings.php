<?php

declare(strict_types=1);

namespace App\Application\Settings\Command;

use App\Application\Command\CommandInterface;

final class UpdateSmtpSettings implements CommandInterface
{
    /** @param string|null $password null = Passwort unverändert lassen (Formular zeigt das gespeicherte Passwort nie im Klartext an). */
    public function __construct(
        public readonly string $host,
        public readonly string $port,
        public readonly string $encryption,
        public readonly ?string $username,
        public readonly ?string $password,
        public readonly string $fromAddress,
        public readonly string $fromName,
        public readonly ?string $replyToAddress,
        public readonly ?string $archiveBccAddress,
        // Immer ein konkretes Array vom Controller (siehe
        // Request::inputArray()), NIE null — ein leeres Array bedeutet
        // "keine Kategorie ausgewählt", nicht "nicht übermittelt".
        public readonly array $archiveCategories,
    ) {
    }
}
