<?php

declare(strict_types=1);

namespace App\Application\Calendar\Command;

/** Trägt das Klartext-Passwort, das nur direkt nach der Erstellung einmalig angezeigt werden kann (siehe CreateCalDavCredentialHandler). */
final class CreatedCalDavCredential
{
    public function __construct(
        public readonly string $id,
        public readonly string $rawPassword,
    ) {
    }
}
