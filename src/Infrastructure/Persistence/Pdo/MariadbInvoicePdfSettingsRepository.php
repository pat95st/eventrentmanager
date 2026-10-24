<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Settings\InvoicePdfSettings;
use App\Domain\Settings\InvoicePdfSettingsRepositoryInterface;
use PDO;
use RuntimeException;

final class MariadbInvoicePdfSettingsRepository implements InvoicePdfSettingsRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function get(): InvoicePdfSettings
    {
        $stmt = $this->pdo->query('SELECT * FROM invoice_pdf_settings WHERE id = 1');
        $row = $stmt->fetch();

        if ($row === false) {
            throw new RuntimeException('Rechnungs-PDF-Vorlage wurde nicht gefunden — fehlt die Migration 032?');
        }

        return new InvoicePdfSettings(
            introHtml: $row['intro_html'],
            closingHtml: $row['closing_html'],
        );
    }

    public function save(InvoicePdfSettings $settings): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE invoice_pdf_settings SET intro_html = :intro_html, closing_html = :closing_html WHERE id = 1'
        );

        $stmt->execute([
            'intro_html' => $settings->introHtml,
            'closing_html' => $settings->closingHtml,
        ]);
    }
}
