<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Settings\LegalSettings;
use App\Domain\Settings\LegalSettingsRepositoryInterface;
use PDO;
use RuntimeException;

final class MariadbLegalSettingsRepository implements LegalSettingsRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function get(): LegalSettings
    {
        $stmt = $this->pdo->query('SELECT * FROM legal_settings WHERE id = 1');
        $row = $stmt->fetch();

        if ($row === false) {
            // Sollte durch die Migration/das Schema nie vorkommen (die
            // Singleton-Zeile wird dort direkt mit angelegt), aber ein
            // klarer Fehler ist hier besser als eine stille Falscheinstellung.
            throw new RuntimeException('Rechtliche Einstellungen wurden nicht gefunden — fehlt die entsprechende Migration?');
        }

        return new LegalSettings(
            rentalTermsMarkdown: $row['rental_terms_markdown'],
            imprintMode: $row['imprint_mode'],
            imprintMarkdown: $row['imprint_markdown'],
            imprintUrl: $row['imprint_url'],
            privacyMode: $row['privacy_mode'],
            privacyMarkdown: $row['privacy_markdown'],
            privacyUrl: $row['privacy_url'],
        );
    }

    public function save(LegalSettings $settings): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE legal_settings SET
                rental_terms_markdown = :rental_terms_markdown,
                imprint_mode = :imprint_mode, imprint_markdown = :imprint_markdown, imprint_url = :imprint_url,
                privacy_mode = :privacy_mode, privacy_markdown = :privacy_markdown, privacy_url = :privacy_url
             WHERE id = 1'
        );

        $stmt->execute([
            'rental_terms_markdown' => $settings->rentalTermsMarkdown,
            'imprint_mode' => $settings->imprintMode,
            'imprint_markdown' => $settings->imprintMarkdown,
            'imprint_url' => $settings->imprintUrl,
            'privacy_mode' => $settings->privacyMode,
            'privacy_markdown' => $settings->privacyMarkdown,
            'privacy_url' => $settings->privacyUrl,
        ]);
    }
}
