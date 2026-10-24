<?php

declare(strict_types=1);

namespace App\Application\Voucher\Command;

use App\Domain\Voucher\VoucherDiscountType;
use App\Shared\Money;
use DateTimeImmutable;

/**
 * Gemeinsame Validierung für Create-/UpdateVoucherHandler (Rabatt-Art/-Wert,
 * Gültigkeitszeitraum, Notiz), damit die Regeln nicht an zwei Stellen
 * auseinanderlaufen können (analog zu ValidatesSupplierInput).
 */
trait ValidatesVoucherInput
{
    /**
     * @return array{0: array<string, string>, 1: array{
     *     discountType: VoucherDiscountType|null, discountValue: string,
     *     validFrom: DateTimeImmutable|null, validUntil: DateTimeImmutable|null, notes: string|null
     * }}
     */
    private function validateVoucherInput(
        string $rawDiscountType,
        string $rawDiscountValue,
        ?string $rawValidFrom,
        ?string $rawValidUntil,
        ?string $notes,
    ): array {
        $errors = [];

        $discountType = VoucherDiscountType::tryFrom($rawDiscountType);
        if ($discountType === null) {
            $errors['discountType'] = 'Bitte eine gültige Rabatt-Art (Prozent/Betrag) wählen.';
        }

        $discountValue = '0.00';
        if ($discountType === VoucherDiscountType::Percentage) {
            $rawPercent = str_replace(',', '.', trim($rawDiscountValue));
            $percentValue = filter_var($rawPercent, FILTER_VALIDATE_FLOAT);
            if ($percentValue === false || $percentValue <= 0 || $percentValue > 100) {
                $errors['discountValue'] = 'Bitte einen gültigen Prozentsatz zwischen 0 und 100 angeben.';
            } else {
                $discountValue = number_format($percentValue, 2, '.', '');
            }
        } elseif ($discountType === VoucherDiscountType::FixedAmount) {
            $normalizedAmount = Money::normalize($rawDiscountValue);
            if ($normalizedAmount === false || $normalizedAmount === null || (float) $normalizedAmount <= 0) {
                $errors['discountValue'] = 'Bitte einen gültigen Betrag größer 0 angeben.';
            } else {
                $discountValue = $normalizedAmount;
            }
        }

        $validFrom = null;
        $trimmedValidFrom = $rawValidFrom !== null ? trim($rawValidFrom) : '';
        if ($trimmedValidFrom !== '') {
            $parsed = DateTimeImmutable::createFromFormat('Y-m-d', $trimmedValidFrom);
            if ($parsed === false) {
                $errors['validFrom'] = 'Bitte ein gültiges Gültig-ab-Datum angeben.';
            } else {
                $validFrom = $parsed;
            }
        }

        $validUntil = null;
        $trimmedValidUntil = $rawValidUntil !== null ? trim($rawValidUntil) : '';
        if ($trimmedValidUntil !== '') {
            $parsed = DateTimeImmutable::createFromFormat('Y-m-d', $trimmedValidUntil);
            if ($parsed === false) {
                $errors['validUntil'] = 'Bitte ein gültiges Gültig-bis-Datum angeben.';
            } else {
                $validUntil = $parsed;
            }
        }

        if ($validFrom !== null && $validUntil !== null && $validUntil->format('Y-m-d') < $validFrom->format('Y-m-d')) {
            $errors['validUntil'] = 'Das Gültig-bis-Datum darf nicht vor dem Gültig-ab-Datum liegen.';
        }

        $notes = $notes !== null ? trim($notes) : null;
        if ($notes === '') {
            $notes = null;
        }

        return [$errors, [
            'discountType' => $discountType,
            'discountValue' => $discountValue,
            'validFrom' => $validFrom,
            'validUntil' => $validUntil,
            'notes' => $notes,
        ]];
    }
}
