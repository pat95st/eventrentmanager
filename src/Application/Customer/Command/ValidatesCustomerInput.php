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
}
