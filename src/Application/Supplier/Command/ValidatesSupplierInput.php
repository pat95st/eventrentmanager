<?php

declare(strict_types=1);

namespace App\Application\Supplier\Command;

use App\Domain\Supplier\SupplierType;

/**
 * Gemeinsame Validierung für Create-/UpdateSupplierHandler, damit die Regeln
 * nicht an zwei Stellen auseinanderlaufen können.
 */
trait ValidatesSupplierInput
{
    /**
     * @return array{0: array<string, string>, 1: array{
     *     name: string, type: SupplierType|null, street: string, postalCode: string,
     *     city: string, phone: string|null, email: string|null, customerNumber: string|null
     * }}
     */
    private function validateSupplierInput(
        string $name,
        string $rawType,
        string $street,
        string $postalCode,
        string $city,
        ?string $phone,
        ?string $email,
        ?string $customerNumber = null,
    ): array {
        $errors = [];

        $name = trim($name);
        if ($name === '') {
            $errors['name'] = 'Bitte einen Namen angeben.';
        }

        $type = SupplierType::tryFrom($rawType);
        if ($type === null) {
            $errors['type'] = 'Bitte eine gültige Art (Kauf/Zumietung) wählen.';
        }

        $street = trim($street);
        if ($street === '') {
            $errors['street'] = 'Bitte Straße und Hausnummer angeben.';
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

        $customerNumber = $customerNumber !== null ? trim($customerNumber) : null;
        if ($customerNumber === '') {
            $customerNumber = null;
        }

        return [$errors, [
            'name' => $name,
            'type' => $type,
            'street' => $street,
            'postalCode' => $postalCode,
            'city' => $city,
            'phone' => $phone,
            'email' => $email,
            'customerNumber' => $customerNumber,
        ]];
    }
}
