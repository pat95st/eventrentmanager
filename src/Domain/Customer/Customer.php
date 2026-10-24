<?php

declare(strict_types=1);

namespace App\Domain\Customer;

use DateTimeImmutable;

final class Customer
{
    public function __construct(
        public readonly string $id,
        public CustomerType $type,
        public ?string $companyName,  // nur bei Art = Geschäftlich befüllt
        public string $firstName,
        public string $lastName,
        public string $street,        // Straße/Hausnummer
        public ?string $addressLine2, // Adresszusatz, z. B. "c/o", Etage, Gebäudeteil
        public string $postalCode,
        public string $city,
        public ?string $phone,
        public ?string $email,
        // Chat-ID des Kunden beim Firmen-Telegram-Bot (siehe
        // App\Domain\Messaging) — KEINE Benutzername/Telefonnummer-Angabe:
        // der Kunde muss den Bot einmal selbst anschreiben, erst dadurch
        // erhält der Bot eine chat_id, an die er aktiv schreiben darf.
        public ?string $telegramChatId,
        public readonly DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {
    }

    /** Anzeigename: bei Geschäftskunden der Firmenname, sonst Vor- + Nachname. */
    public function displayName(): string
    {
        if ($this->type === CustomerType::Geschaeftlich && $this->companyName !== null && trim($this->companyName) !== '') {
            return $this->companyName;
        }

        return trim($this->firstName . ' ' . $this->lastName);
    }
}
