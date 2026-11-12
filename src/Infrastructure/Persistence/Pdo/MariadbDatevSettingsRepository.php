<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Datev\DatevAccountRole;
use App\Domain\Datev\DatevChartOfAccounts;
use App\Domain\Datev\DatevDebtorMode;
use App\Domain\Datev\DatevExportInterval;
use App\Domain\Datev\DatevSettings;
use App\Domain\Datev\DatevSettingsRepositoryInterface;
use PDO;
use RuntimeException;

final class MariadbDatevSettingsRepository implements DatevSettingsRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function get(): DatevSettings
    {
        $row = $this->pdo->query('SELECT * FROM datev_settings WHERE id = 1')->fetch();

        if ($row === false) {
            throw new RuntimeException('DATEV-Einstellungen wurden nicht gefunden — fehlt Migration 143?');
        }

        $accounts = [];
        foreach (DatevAccountRole::cases() as $role) {
            $value = $row['account_' . $role->value] ?? null;
            if ($value !== null) {
                $accounts[$role->value] = (int) $value;
            }
        }

        return new DatevSettings(
            enabled: (bool) $row['enabled'],
            consultantNumber: $row['consultant_number'] !== null ? (int) $row['consultant_number'] : null,
            clientNumber: $row['client_number'] !== null ? (int) $row['client_number'] : null,
            chartOfAccounts: DatevChartOfAccounts::tryFrom((string) $row['chart_of_accounts']) ?? DatevChartOfAccounts::Skr03,
            accountLength: (int) $row['account_length'],
            fiscalYearStartMonth: (int) $row['fiscal_year_start_month'],
            debtorMode: DatevDebtorMode::tryFrom((string) $row['debtor_mode']) ?? DatevDebtorMode::Collective,
            debtorAccount: $row['debtor_account'] !== null ? (int) $row['debtor_account'] : null,
            creditorAccount: $row['creditor_account'] !== null ? (int) $row['creditor_account'] : null,
            accounts: $accounts,
            receiptTaxKey: $row['receipt_tax_key'],
            includeReceipts: (bool) $row['include_receipts'],
            includeDocuments: (bool) $row['include_documents'],
            advisorEmail: $row['advisor_email'],
            autoSendEnabled: (bool) $row['auto_send_enabled'],
            interval: DatevExportInterval::tryFrom((string) $row['export_interval']) ?? DatevExportInterval::Monthly,
            sendDay: (int) $row['send_day'],
        );
    }

    public function save(DatevSettings $settings): void
    {
        $params = [
            'enabled' => $settings->enabled ? 1 : 0,
            'consultant_number' => $settings->consultantNumber,
            'client_number' => $settings->clientNumber,
            'chart_of_accounts' => $settings->chartOfAccounts->value,
            'account_length' => $settings->accountLength,
            'fiscal_year_start_month' => $settings->fiscalYearStartMonth,
            'debtor_mode' => $settings->debtorMode->value,
            'debtor_account' => $settings->debtorAccount,
            'creditor_account' => $settings->creditorAccount,
            'receipt_tax_key' => $settings->receiptTaxKey,
            'include_receipts' => $settings->includeReceipts ? 1 : 0,
            'include_documents' => $settings->includeDocuments ? 1 : 0,
            'advisor_email' => $settings->advisorEmail,
            'auto_send_enabled' => $settings->autoSendEnabled ? 1 : 0,
            'export_interval' => $settings->interval->value,
            'send_day' => $settings->sendDay,
        ];
        foreach (DatevAccountRole::cases() as $role) {
            $params['account_' . $role->value] = $settings->accounts[$role->value] ?? null;
        }

        $assignments = implode(', ', array_map(static fn (string $column) => "{$column} = :{$column}", array_keys($params)));
        $this->pdo->prepare("UPDATE datev_settings SET {$assignments} WHERE id = 1")->execute($params);
    }
}
