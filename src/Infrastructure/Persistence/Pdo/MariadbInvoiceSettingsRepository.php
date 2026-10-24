<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Settings\InvoiceSettings;
use App\Domain\Settings\InvoiceSettingsRepositoryInterface;
use PDO;
use RuntimeException;

final class MariadbInvoiceSettingsRepository implements InvoiceSettingsRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function get(): InvoiceSettings
    {
        $stmt = $this->pdo->query('SELECT * FROM invoice_settings WHERE id = 1');
        $row = $stmt->fetch();

        if ($row === false) {
            // Sollte durch die Migration/das Schema nie vorkommen (die
            // Singleton-Zeile wird dort direkt mit angelegt), aber ein
            // klarer Fehler ist hier besser als eine stille Falscheinstellung.
            throw new RuntimeException('Rechnungseinstellungen wurden nicht gefunden — fehlt die Migration 031?');
        }

        return new InvoiceSettings(
            isSmallBusiness: (bool) $row['is_small_business'],
            vatRatePercent: $row['vat_rate_percent'],
            taxNumber: $row['tax_number'],
            vatId: $row['vat_id'],
            paymentTermDays: (int) $row['payment_term_days'],
            bankName: $row['bank_name'],
            iban: $row['iban'],
            bic: $row['bic'],
        );
    }

    public function save(InvoiceSettings $settings): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE invoice_settings SET
                is_small_business = :is_small_business, vat_rate_percent = :vat_rate_percent,
                tax_number = :tax_number, vat_id = :vat_id,
                payment_term_days = :payment_term_days, bank_name = :bank_name, iban = :iban, bic = :bic
             WHERE id = 1'
        );

        $stmt->execute([
            'is_small_business' => $settings->isSmallBusiness ? 1 : 0,
            'vat_rate_percent' => $settings->vatRatePercent,
            'tax_number' => $settings->taxNumber,
            'vat_id' => $settings->vatId,
            'payment_term_days' => $settings->paymentTermDays,
            'bank_name' => $settings->bankName,
            'iban' => $settings->iban,
            'bic' => $settings->bic,
        ]);
    }
}
