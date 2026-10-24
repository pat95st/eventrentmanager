<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Settings\OfferPdfSettings;
use App\Domain\Settings\OfferPdfSettingsRepositoryInterface;
use PDO;
use RuntimeException;

final class MariadbOfferPdfSettingsRepository implements OfferPdfSettingsRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function get(): OfferPdfSettings
    {
        $stmt = $this->pdo->query('SELECT * FROM offer_pdf_settings WHERE id = 1');
        $row = $stmt->fetch();

        if ($row === false) {
            // Sollte durch die Migration/das Schema nie vorkommen (die
            // Singleton-Zeile wird dort direkt mit angelegt), aber ein
            // klarer Fehler ist hier besser als eine stille Falscheinstellung.
            throw new RuntimeException('Angebots-PDF-Vorlage wurde nicht gefunden — fehlt die Migration 030?');
        }

        return new OfferPdfSettings(
            introHtml: $row['intro_html'],
            closingHtml: $row['closing_html'],
        );
    }

    public function save(OfferPdfSettings $settings): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE offer_pdf_settings SET intro_html = :intro_html, closing_html = :closing_html WHERE id = 1'
        );

        $stmt->execute([
            'intro_html' => $settings->introHtml,
            'closing_html' => $settings->closingHtml,
        ]);
    }
}
