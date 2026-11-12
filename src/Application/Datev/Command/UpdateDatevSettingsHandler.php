<?php

declare(strict_types=1);

namespace App\Application\Datev\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Datev\DatevAccountRole;
use App\Domain\Datev\DatevChartOfAccounts;
use App\Domain\Datev\DatevDebtorMode;
use App\Domain\Datev\DatevExportInterval;
use App\Domain\Datev\DatevSettings;
use App\Domain\Datev\DatevSettingsRepositoryInterface;
use App\Domain\Exception\ValidationException;

final class UpdateDatevSettingsHandler implements CommandHandlerInterface
{
    public function __construct(private readonly DatevSettingsRepositoryInterface $repository)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof UpdateDatevSettings);

        $errors = [];

        $consultantNumber = $this->optionalInt($command->consultantNumber);
        if ($consultantNumber === false || ($consultantNumber !== null && ($consultantNumber < 1001 || $consultantNumber > 9999999))) {
            $errors['datev_consultant_number'] = 'Die Beraternummer ist eine Zahl von 1001 bis 9999999.';
        }

        $clientNumber = $this->optionalInt($command->clientNumber);
        if ($clientNumber === false || ($clientNumber !== null && ($clientNumber < 1 || $clientNumber > 99999))) {
            $errors['datev_client_number'] = 'Die Mandantennummer ist eine Zahl von 1 bis 99999.';
        }

        $chart = DatevChartOfAccounts::tryFrom((string) $command->chartOfAccounts) ?? DatevChartOfAccounts::Skr03;

        $accountLength = $this->optionalInt($command->accountLength) ?? 4;
        if ($accountLength === false || $accountLength < 4 || $accountLength > 8) {
            $errors['datev_account_length'] = 'Die Sachkontenlänge muss zwischen 4 und 8 liegen.';
            $accountLength = 4;
        }

        $fiscalYearStartMonth = $this->optionalInt($command->fiscalYearStartMonth) ?? 1;
        if ($fiscalYearStartMonth === false || $fiscalYearStartMonth < 1 || $fiscalYearStartMonth > 12) {
            $errors['datev_fiscal_year_start_month'] = 'Bitte einen Monat von 1 bis 12 wählen.';
            $fiscalYearStartMonth = 1;
        }

        $settings = new DatevSettings(
            chartOfAccounts: $chart,
            accountLength: $accountLength,
            fiscalYearStartMonth: $fiscalYearStartMonth,
            debtorMode: DatevDebtorMode::tryFrom((string) $command->debtorMode) ?? DatevDebtorMode::Collective,
        );

        $debtorAccount = $this->optionalInt($command->debtorAccount);
        if ($debtorAccount === false || ($debtorAccount !== null && !$settings->isDebtorAccount($debtorAccount))) {
            $errors['datev_debtor_account'] = 'Debitorenkonten liegen bei dieser Sachkontenlänge zwischen ' . (10 ** $accountLength) . ' und ' . (7 * 10 ** $accountLength - 1) . '.';
            $debtorAccount = null;
        }

        $creditorAccount = $this->optionalInt($command->creditorAccount);
        if ($creditorAccount === false || ($creditorAccount !== null && !$settings->isCreditorAccount($creditorAccount))) {
            $errors['datev_creditor_account'] = 'Kreditorenkonten liegen bei dieser Sachkontenlänge zwischen ' . (7 * 10 ** $accountLength) . ' und ' . (10 ** ($accountLength + 1) - 1) . '.';
            $creditorAccount = null;
        }

        $accounts = [];
        foreach (DatevAccountRole::cases() as $role) {
            $value = $this->optionalInt($command->accounts[$role->value] ?? null);
            if ($value === null) {
                continue;
            }
            if ($value === false || !$settings->isGeneralLedgerAccount($value)) {
                $errors['datev_account_' . $role->value] = 'Sachkonten haben bei dieser Sachkontenlänge höchstens ' . $accountLength . ' Stellen.';

                continue;
            }
            $accounts[$role->value] = $value;
        }

        $receiptTaxKey = trim((string) $command->receiptTaxKey);
        if ($receiptTaxKey !== '' && preg_match('/^\d{1,4}$/', $receiptTaxKey) !== 1) {
            $errors['datev_receipt_tax_key'] = 'Der BU-Schlüssel besteht aus 1 bis 4 Ziffern (z. B. 9 für 19 % Vorsteuer).';
        }

        $advisorEmail = trim((string) $command->advisorEmail);
        if ($advisorEmail !== '' && filter_var($advisorEmail, FILTER_VALIDATE_EMAIL) === false) {
            $errors['datev_advisor_email'] = 'Bitte eine gültige E-Mail-Adresse angeben.';
        }

        $sendDay = $this->optionalInt($command->sendDay) ?? 5;
        if ($sendDay === false || $sendDay < 1 || $sendDay > 28) {
            $errors['datev_send_day'] = 'Der Versandtag muss zwischen 1 und 28 liegen.';
            $sendDay = 5;
        }

        if ($command->autoSendEnabled) {
            if ($advisorEmail === '') {
                $errors['datev_advisor_email'] ??= 'Für den automatischen Versand wird die E-Mail-Adresse des Steuerberaters benötigt.';
            }
            if ($consultantNumber === null || $clientNumber === null) {
                $errors['datev_consultant_number'] ??= 'Für den automatischen Versand werden Berater- und Mandantennummer benötigt.';
            }
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $settings->enabled = $command->enabled;
        $settings->consultantNumber = $consultantNumber;
        $settings->clientNumber = $clientNumber;
        $settings->debtorAccount = $debtorAccount;
        $settings->creditorAccount = $creditorAccount;
        $settings->accounts = $accounts;
        $settings->receiptTaxKey = $receiptTaxKey !== '' ? $receiptTaxKey : null;
        $settings->includeReceipts = $command->includeReceipts;
        $settings->includeDocuments = $command->includeDocuments;
        $settings->advisorEmail = $advisorEmail !== '' ? $advisorEmail : null;
        $settings->autoSendEnabled = $command->enabled && $command->autoSendEnabled;
        $settings->interval = DatevExportInterval::tryFrom((string) $command->interval) ?? DatevExportInterval::Monthly;
        $settings->sendDay = $sendDay;

        $this->repository->save($settings);

        return null;
    }

    /** NULL bei leerer Eingabe, false bei ungültiger Eingabe. */
    private function optionalInt(?string $value): int|false|null
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        return preg_match('/^\d{1,10}$/', $value) === 1 ? (int) $value : false;
    }
}
