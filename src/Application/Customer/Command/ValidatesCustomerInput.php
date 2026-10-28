<?php

declare(strict_types=1);

namespace App\Application\Customer\Command;

use App\Domain\Customer\CustomerType;

/**
 * Gemeinsame Validierung für Create-/UpdateCustomerHandler, damit die Regeln
 * nicht an zwei Stellen auseinanderlaufen können.
 */
trait ValidatesCustomerInput
{
    /**
     * @return array{0: array<string, string>, 1: array{
     *     type: CustomerType|null, companyName: string|null, firstName: string, lastName: string,
     *     street: string, addressLine2: string|null, postalCode: string, city: string,
     *     phone: string|null, email: string|null, telegramChatId: string|null
     * }}
     */
    private function validateCustomerInput(
        string $rawType,
        ?string $companyName,
        string $firstName,
        string $lastName,
        string $street,
        ?string $addressLine2,
        string $postalCode,
        string $city,
        ?string $phone,
        ?string $email,
        ?string $telegramChatId = null,
    ): array {
        $errors = [];

        $type = CustomerType::tryFrom($rawType);
        if ($type === null) {
            $errors['type'] = 'Bitte eine gültige Art (Privat/Geschäftlich) wählen.';
        }

        $companyName = $companyName !== null ? trim($companyName) : null;
        if ($type === CustomerType::Geschaeftlich && ($companyName === null || $companyName === '')) {
            $errors['companyName'] = 'Bitte einen Firmennamen angeben.';
        }
        if ($type !== CustomerType::Geschaeftlich) {
            $companyName = null;
        }

        $firstName = trim($firstName);
        if ($firstName === '') {
            $errors['firstName'] = 'Bitte einen Vornamen angeben.';
        }

        $lastName = trim($lastName);
        if ($lastName === '') {
            $errors['lastName'] = 'Bitte einen Nachnamen angeben.';
        }

        $street = trim($street);
        if ($street === '') {
            $errors['street'] = 'Bitte Straße und Hausnummer angeben.';
        }

        $addressLine2 = $addressLine2 !== null ? trim($addressLine2) : null;
        if ($addressLine2 === '') {
            $addressLine2 = null;
        }

        $postalCode = trim($postalCode);
        if ($postalCode === '') {
            $errors['postalCode'] = 'Bitte eine Postleitzahl angeben.';
        }

        $city = trim($city);
        if ($city === '') {
            $errors['city'] = 'Bitte einen Ort angeben.';
        }

        $phone = $phone !== null ? trim($phone) : null;
        if ($phone === '') {
            $phone = null;
        }

        $email = $email !== null ? trim($email) : null;
        if ($email === '') {
            $email = null;
        }
        if ($email !== null && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = 'Bitte eine gültige E-Mail-Adresse angeben.';
        }

        // Telegram-chat_ids sind rein numerisch (ggf. mit führendem "-" bei
        // Gruppen-Chats, die hier aber nicht relevant sind) — eine strengere
        // Prüfung als "nur Ziffern" lohnt sich nicht, da der Admin die ID
        // ohnehin nur aus einer Bot-Antwort kopiert.
        $telegramChatId = $telegramChatId !== null ? trim($telegramChatId) : null;
        if ($telegramChatId === '') {
            $telegramChatId = null;
        }
        if ($telegramChatId !== null && !ctype_digit($telegramChatId)) {
            $errors['telegramChatId'] = 'Bitte nur die numerische Telegram-Chat-ID angeben.';
        }

        return [$errors, [
            'type' => $type,
            'companyName' => $companyName,
            'firstName' => $firstName,
            'lastName' => $lastName,
            'street' => $street,
            'addressLine2' => $addressLine2,
            'postalCode' => $postalCode,
            'city' => $city,
            'phone' => $phone,
            'email' => $email,
            'telegramChatId' => $telegramChatId,
        ]];
    }

    /**
     * Validiert die Ansprechpartner-Zeilen (siehe customers/form.php,
     * repeatable-row-Muster analog zu den Arbeitszeit-Positionen in
     * offers/form.php). Eine Zeile ohne Namen wird stillschweigend
     * übersprungen (= eine leere Vorlagenzeile, die nicht abgeschickt
     * wurde), statt einen Validierungsfehler auszulösen.
     *
     * @param array<int, array{name?: string, job_title?: string, phone?: string, email?: string, street?: string, address_line2?: string, postal_code?: string, city?: string}> $rawContacts
     * @return array{0: array<string, string>, 1: array<int, array{name: string, jobTitle: ?string, phone: ?string, email: ?string, street: ?string, addressLine2: ?string, postalCode: ?string, city: ?string}>}
     */
    private function validateCustomerContacts(array $rawContacts): array
    {
        $errors = [];
        $contacts = [];

        foreach ($rawContacts as $rawContact) {
            $name = trim((string) ($rawContact['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $jobTitle = trim((string) ($rawContact['job_title'] ?? ''));
            $phone = trim((string) ($rawContact['phone'] ?? ''));
            $email = trim((string) ($rawContact['email'] ?? ''));
            // Nutzerwunsch "eigene Adresse je Ansprechpartner" (Migration
            // 104): alle optional, bleiben sie leer, gilt auf Dokumenten
            // weiterhin die Adresse des Kunden (siehe CustomerContact::
            // hasOwnAddress()).
            $street = trim((string) ($rawContact['street'] ?? ''));
            $addressLine2 = trim((string) ($rawContact['address_line2'] ?? ''));
            $postalCode = trim((string) ($rawContact['postal_code'] ?? ''));
            $city = trim((string) ($rawContact['city'] ?? ''));

            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                $errors['contacts'] = 'Bitte bei jedem Ansprechpartner eine gültige E-Mail-Adresse angeben (oder das Feld leer lassen).';

                continue;
            }

            $contacts[] = [
                'name' => $name,
                'jobTitle' => $jobTitle !== '' ? $jobTitle : null,
                'phone' => $phone !== '' ? $phone : null,
                'email' => $email !== '' ? $email : null,
                'street' => $street !== '' ? $street : null,
                'addressLine2' => $addressLine2 !== '' ? $addressLine2 : null,
                'postalCode' => $postalCode !== '' ? $postalCode : null,
                'city' => $city !== '' ? $city : null,
            ];
        }

        return [$errors, $contacts];
    }
}
