<?php

declare(strict_types=1);

namespace App\Application\Settings\Command;

use App\Domain\Exception\ValidationException;
use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Settings\InvoiceSettings;
use App\Domain\Settings\InvoiceSettingsRepositoryInterface;

/**
 * Nur leichte Validierung — kein IBAN-Prüfsummen-Check o. Ä., siehe
 * Aufgabenstellung. vatRatePercent muss numerisch 0–100 sein,
 * paymentTermDays eine positive Ganzzahl.
 */
final class UpdateInvoiceSettingsHandler implements CommandHandlerInterface
{
    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof UpdateInvoiceSettings);

        $errors = [];

        $vatRatePercent = str_replace(',', '.', trim($command->vatRatePercent));
        $vatRateValue = filter_var($vatRatePercent, FILTER_VALIDATE_FLOAT);
        if ($vatRateValue === false || $vatRateValue < 0 || $vatRateValue > 100) {
            $errors['vatRatePercent'] = 'Bitte einen gültigen Steuersatz zwischen 0 und 100 angeben.';
        }

        $paymentTermDays = filter_var(trim($command->paymentTermDays), FILTER_VALIDATE_INT);
        if ($paymentTermDays === false || $paymentTermDays < 1) {
            $errors['paymentTermDays'] = 'Bitte ein Zahlungsziel von mindestens 1 Tag angeben.';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $this->settingsRepository->save(new InvoiceSettings(
            isSmallBusiness: $command->isSmallBusiness,
            vatRatePercent: number_format((float) $vatRateValue, 2, '.', ''),
            taxNumber: $this->nullIfEmpty($command->taxNumber),
            vatId: $this->nullIfEmpty($command->vatId),
            paymentTermDays: (int) $paymentTermDays,
            bankName: $this->nullIfEmpty($command->bankName),
            iban: $this->nullIfEmpty($command->iban),
            bic: $this->nullIfEmpty($command->bic),
        ));

        return null;
    }

    public function __construct(private readonly InvoiceSettingsRepositoryInterface $settingsRepository)
    {
    }

    private function nullIfEmpty(?string $value): ?string
    {
        return $value === null || trim($value) === '' ? null : trim($value);
    }
}
