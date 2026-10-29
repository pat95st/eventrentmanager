<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Settings\PdfDesignSettings;
use App\Domain\Settings\PdfDesignSettingsRepositoryInterface;
use PDO;
use RuntimeException;

final class MariadbPdfDesignSettingsRepository implements PdfDesignSettingsRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function get(): PdfDesignSettings
    {
        $stmt = $this->pdo->query('SELECT * FROM pdf_design_settings WHERE id = 1');
        $row = $stmt->fetch();

        if ($row === false) {
            // Sollte durch die Migration nie vorkommen (die Singleton-Zeile
            // wird dort direkt mit angelegt), siehe MariadbInvoiceSettingsRepository
            // für das identische Muster.
            throw new RuntimeException('PDF-Design-Einstellungen wurden nicht gefunden — fehlt die Migration 066?');
        }

        return new PdfDesignSettings(
            accentColorR: (int) $row['accent_r'],
            accentColorG: (int) $row['accent_g'],
            accentColorB: (int) $row['accent_b'],
            logoPosition: (string) $row['logo_position'],
            currencyDisplay: (string) ($row['currency_display'] ?? PdfDesignSettings::CURRENCY_DISPLAY_CODE),
        );
    }

    public function save(PdfDesignSettings $settings): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE pdf_design_settings SET
                accent_r = :accent_r, accent_g = :accent_g, accent_b = :accent_b, logo_position = :logo_position, currency_display = :currency_display
             WHERE id = 1'
        );

        $stmt->execute([
            'accent_r' => $settings->accentColorR,
            'accent_g' => $settings->accentColorG,
            'accent_b' => $settings->accentColorB,
            'logo_position' => $settings->logoPosition,
            'currency_display' => $settings->currencyDisplay,
        ]);
    }
}
