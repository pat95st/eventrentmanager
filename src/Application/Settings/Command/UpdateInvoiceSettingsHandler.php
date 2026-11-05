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

        $current = $this->settingsRepository->get();

        $paymentTermDays = $command->paymentTermDays === null
            ? $current->paymentTermDays
            : filter_var(trim($command->paymentTermDays), FILTER_VALIDATE_INT);
        if ($paymentTermDays === false || $paymentTermDays < 1) {
            $errors['paymentTermDays'] = 'Bitte ein Zahlungsziel von mindestens 1 Tag angeben.';
        }

        // Regelbesteuerung: auf Rechnungen ist die Steuernummer oder die
        // USt-IdNr. Pflicht (§ 14 Abs. 4 Nr. 2 UStG).
        if (!$command->isSmallBusiness && $this->nullIfEmpty($command->taxNumber) === null && $this->nullIfEmpty($command->vatId) === null) {
            $errors['taxNumber'] = 'Ohne Kleinunternehmerregelung muss auf Rechnungen die Steuernummer oder die USt-IdNr. stehen – bitte mindestens eines davon angeben.';
        }
        $note = $command->smallBusinessNote === null ? $current->smallBusinessNote : $this->nullIfEmpty($command->smallBusinessNote);
        if ($note !== null && mb_strlen($note) > 500) {
            $errors['smallBusinessNote'] = 'Der Hinweistext darf höchstens 500 Zeichen lang sein.';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $keep = fn (?string $new, ?string $old) => $new === null ? $old : $this->nullIfEmpty($new);
        $this->settingsRepository->save(new InvoiceSettings(
            isSmallBusiness: $command->isSmallBusiness,
            vatRatePercent: number_format((float) $vatRateValue, 2, '.', ''),
            taxNumber: $this->nullIfEmpty($command->taxNumber),
            vatId: $this->nullIfEmpty($command->vatId),
            paymentTermDays: (int) $paymentTermDays,
            bankName: $command->bankName === null ? $current->bankName : $this->nullIfEmpty($command->bankName),
            iban: $command->iban === null ? $current->iban : $this->nullIfEmpty($command->iban),
            bic: $command->bic === null ? $current->bic : $this->nullIfEmpty($command->bic),
            taxOffice: $keep($command->taxOffice, $current->taxOffice),
            legalForm: $keep($command->legalForm, $current->legalForm),
            registerCourt: $keep($command->registerCourt, $current->registerCourt),
            registerNumber: $keep($command->registerNumber, $current->registerNumber),
            managingDirectors: $keep($command->managingDirectors, $current->managingDirectors),
            smallBusinessNote: $note,
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
