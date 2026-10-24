<?php

declare(strict_types=1);

namespace App\Application\User\Command;

use App\Application\Command\CommandInterface;

final class CreateUser implements CommandInterface
{
    /**
     * @param string[] $permissions Rohe Permission-Werte aus dem Formular (Checkboxen).
     * @param string $password Leerer String = kein Erstpasswort ("Per E-Mail einladen") —
     *        der Benutzer erhält stattdessen einen Setup-Link (siehe CreateUserHandler).
     * @param string $originUrl Vollständige Origin der aktuellen Anfrage (z. B.
     *        "https://verleih.example.com"), zum Aufbau des Setup-Links —
     *        siehe App\Infrastructure\Http\Origin. Nur relevant, wenn $password leer ist.
     */
    public function __construct(
        public readonly string $email,
        public readonly string $firstName,
        public readonly string $lastName,
        public readonly string $password,
        public readonly array $permissions,
        public readonly bool $mustSetupTwoFactor,
        public readonly string $originUrl,
    ) {
    }
}
