<?php

declare(strict_types=1);

namespace App\Domain\Customer;

/**
 * Ein Ansprechpartner eines Kunden (siehe Migration 100) — ein Kunde kann
 * mehrere davon haben, insbesondere bei Firmenkunden, bei denen die
 * zuständige Person öfter wechselt als der Kunde selbst.
 *
 * Nutzerwunsch "eigene Adresse/Telefon je Ansprechpartner" (Migration 104):
 * $phone existierte bereits je Ansprechpartner; $street/$addressLine2/
 * $postalCode/$city sind optional — bleiben sie alle leer, gilt für
 * Dokumente mit diesem Ansprechpartner weiterhin die Adresse des Kunden
 * selbst (siehe hasOwnAddress()).
 */
final class CustomerContact
{
    public function __construct(
        public readonly string $id,
        public readonly string $customerId,
        public string $name,
        public ?string $jobTitle,
        public ?string $phone,
        public ?string $email,
        public int $sortOrder,
        public ?string $street = null,
        public ?string $addressLine2 = null,
        public ?string $postalCode = null,
        public ?string $city = null,
    ) {
    }

    /**
     * True, wenn für diesen Ansprechpartner eine eigene Adresse gepflegt
     * wurde (mindestens Straße oder Postleitzahl/Ort) — dann wird diese auf
     * Dokumenten statt der Kundenadresse verwendet (siehe PDF-Generatoren).
     */
    public function hasOwnAddress(): bool
    {
        return trim((string) $this->street) !== ''
            || trim((string) $this->postalCode) !== ''
            || trim((string) $this->city) !== '';
    }
}
