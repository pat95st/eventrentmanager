<?php

declare(strict_types=1);

namespace App\Application\Settings\Command;

/**
 * Gemeinsame Validierung für Create-/UpdatePaymentMethodHandler.
 */
final class ValidatesPaymentMethodInput
{
    /** @return array{0: string, 1: int, 2: ?string, 3: array<string, string>} [name, paymentTermDays, noteText, errors] */
    public static function validate(string $name, string $paymentTermDays, ?string $noteText): array
    {
        $errors = [];

        $name = trim($name);
        if ($name === '') {
            $errors['name'] = 'Bitte einen Namen angeben.';
        }

        $days = filter_var(trim($paymentTermDays), FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 365]]);
        if ($days === false) {
            $errors['paymentTermDays'] = 'Bitte ein Zahlungsziel zwischen 0 und 365 Tagen angeben.';
            $days = 0;
        }

        $noteText = $noteText === null || trim($noteText) === '' ? null : trim($noteText);

        return [$name, (int) $days, $noteText, $errors];
    }
}
