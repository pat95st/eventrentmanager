<?php

declare(strict_types=1);

namespace App\Application\Settings;

use App\Domain\Settings\BankAccount;
use App\Domain\Settings\BankAccountRepositoryInterface;
use App\Domain\Settings\PaymentMethod;
use App\Domain\Settings\PaymentMethodRepositoryInterface;

/**
 * Gemeinsame Prüfung/Auflösung der Zahlungsart- und Bankkonto-Auswahl auf
 * Rechnungen, Angeboten und Auftragsbestätigungen (Migrationen 105/106).
 * Leere Auswahl (null) bedeutet überall "Standard" — aufgelöst wird erst bei
 * der Anzeige (resolve*()), damit eine geänderte Standard-Einstellung auch
 * für noch nicht versendete Dokumente greift.
 */
final class PaymentSelection
{
    /**
     * @return array{0: array<string, string>, 1: ?string, 2: ?string} [errors, paymentMethodId, bankAccountId]
     */
    public static function validate(
        ?PaymentMethodRepositoryInterface $paymentMethods,
        ?string $rawPaymentMethodId,
        ?BankAccountRepositoryInterface $bankAccounts = null,
        ?string $rawBankAccountId = null,
    ): array {
        $errors = [];

        $paymentMethodId = $rawPaymentMethodId !== null && trim($rawPaymentMethodId) !== '' ? trim($rawPaymentMethodId) : null;
        if ($paymentMethodId !== null && $paymentMethods !== null && $paymentMethods->findById($paymentMethodId) === null) {
            $errors['paymentMethodId'] = 'Die gewählte Zahlungsart existiert nicht (mehr).';
            $paymentMethodId = null;
        }

        $bankAccountId = $rawBankAccountId !== null && trim($rawBankAccountId) !== '' ? trim($rawBankAccountId) : null;
        if ($bankAccountId !== null && $bankAccounts !== null && $bankAccounts->findById($bankAccountId) === null) {
            $errors['bankAccountId'] = 'Die gewählte Bankverbindung existiert nicht (mehr).';
            $bankAccountId = null;
        }

        return [$errors, $paymentMethodId, $bankAccountId];
    }

    /** Gewählte Zahlungsart, sonst die Standard-Zahlungsart (oder null, wenn keine angelegt ist). */
    public static function resolvePaymentMethod(?PaymentMethodRepositoryInterface $paymentMethods, ?string $paymentMethodId): ?PaymentMethod
    {
        if ($paymentMethods === null) {
            return null;
        }

        return ($paymentMethodId !== null ? $paymentMethods->findById($paymentMethodId) : null) ?? $paymentMethods->findDefault();
    }

    /** Gewähltes Bankkonto, sonst das Standardkonto (oder null, wenn keins angelegt ist). */
    public static function resolveBankAccount(?BankAccountRepositoryInterface $bankAccounts, ?string $bankAccountId): ?BankAccount
    {
        if ($bankAccounts === null) {
            return null;
        }

        return ($bankAccountId !== null ? $bankAccounts->findById($bankAccountId) : null) ?? $bankAccounts->findDefault();
    }
}
