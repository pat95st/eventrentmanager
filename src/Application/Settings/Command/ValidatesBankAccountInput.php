<?php

declare(strict_types=1);

namespace App\Application\Settings\Command;

/**
 * Gemeinsame Validierung für Create-/UpdateBankAccountHandler (analog
 * ValidatesDunningLevelInput).
 */
final class ValidatesBankAccountInput
{
    /**
     * @return array{0: string, 1: ?string, 2: ?string, 3: string, 4: ?string, 5: array<string, string>}
     *         [label, accountHolder, bankName, iban (normalisiert), bic (normalisiert), errors]
     */
    public static function validate(string $label, ?string $accountHolder, ?string $bankName, string $iban, ?string $bic): array
    {
        $errors = [];

        $label = trim($label);
        if ($label === '') {
            $errors['label'] = 'Bitte eine Bezeichnung angeben.';
        }

        $normalizedIban = strtoupper(preg_replace('/\s+/', '', $iban) ?? '');
        if ($normalizedIban === '') {
            $errors['iban'] = 'Bitte eine IBAN angeben.';
        } elseif (!self::isValidIban($normalizedIban)) {
            $errors['iban'] = 'Die IBAN ist ungültig (Format oder Prüfziffer stimmt nicht).';
        }

        $normalizedBic = $bic !== null ? strtoupper(preg_replace('/\s+/', '', $bic) ?? '') : '';
        if ($normalizedBic !== '' && preg_match('/^[A-Z]{6}[A-Z0-9]{2}([A-Z0-9]{3})?$/', $normalizedBic) !== 1) {
            $errors['bic'] = 'Die BIC ist ungültig (8 oder 11 Zeichen).';
        }

        return [
            $label,
            self::nullIfEmpty($accountHolder),
            self::nullIfEmpty($bankName),
            $normalizedIban,
            $normalizedBic !== '' ? $normalizedBic : null,
            $errors,
        ];
    }

    /** ISO 13616: Länderkennung + 2 Prüfziffern + bis zu 30 Zeichen, Modulo 97 = 1. */
    public static function isValidIban(string $iban): bool
    {
        if (preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]{11,30}$/', $iban) !== 1) {
            return false;
        }

        $rearranged = substr($iban, 4) . substr($iban, 0, 4);
        $numeric = '';
        foreach (str_split($rearranged) as $char) {
            $numeric .= ctype_alpha($char) ? (string) (ord($char) - 55) : $char;
        }

        $remainder = 0;
        foreach (str_split($numeric, 7) as $chunk) {
            $remainder = (int) ((string) $remainder . $chunk) % 97;
        }

        return $remainder === 1;
    }

    private static function nullIfEmpty(?string $value): ?string
    {
        return $value === null || trim($value) === '' ? null : trim($value);
    }
}
