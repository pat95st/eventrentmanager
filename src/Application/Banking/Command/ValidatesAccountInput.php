<?php

declare(strict_types=1);

namespace App\Application\Banking\Command;

use App\Domain\Settings\BankAccountRepositoryInterface;
use DateTimeImmutable;

/** Gemeinsame Prüfungen der Kontoführungs-Commands (Migration 131). */
trait ValidatesAccountInput
{
    /** @param array<string, string> $errors */
    private function requireAccount(BankAccountRepositoryInterface $accounts, string $id, array &$errors): void
    {
        $account = $id !== '' ? $accounts->findById($id) : null;
        if ($account === null) {
            $errors['bankAccountId'] = 'Bitte ein gültiges Konto auswählen.';
        } elseif (!$account->ledgerEnabled) {
            $errors['bankAccountId'] = 'Für dieses Konto ist die Kontoführung nicht eingeschaltet (Einstellungen › Bankverbindungen).';
        }
    }

    /** @param array<string, string> $errors */
    private function bookingDate(string $value, array &$errors, string $field = 'bookingDate'): ?DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', trim($value));
        if ($date === false) {
            $errors[$field] = 'Bitte ein gültiges Buchungsdatum angeben.';

            return null;
        }

        return $date;
    }
}
