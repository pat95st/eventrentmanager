<?php

declare(strict_types=1);

namespace App\Application\AddressBook\Command;

/** Trägt das Klartext-Passwort, das nur direkt nach der Erstellung einmalig angezeigt werden kann (siehe CreateCardDavCredentialHandler). */
final class CreatedCardDavCredential
{
    public function __construct(
        public readonly string $id,
        public readonly string $rawPassword,
    ) {
    }
}
