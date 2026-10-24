<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Settings\WhatsappBusinessSettings;
use App\Domain\Settings\WhatsappBusinessSettingsRepositoryInterface;
use PDO;
use RuntimeException;

final class MariadbWhatsappBusinessSettingsRepository implements WhatsappBusinessSettingsRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function get(): WhatsappBusinessSettings
    {
        $stmt = $this->pdo->query('SELECT * FROM whatsapp_business_settings WHERE id = 1');
        $row = $stmt->fetch();

        if ($row === false) {
            throw new RuntimeException('WhatsApp-Business-Einstellungen wurden nicht gefunden — fehlt die entsprechende Migration?');
        }

        return new WhatsappBusinessSettings(
            accessToken: $row['access_token'],
            catalogId: $row['catalog_id'],
            currency: $row['currency'],
        );
    }

    public function save(WhatsappBusinessSettings $settings): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE whatsapp_business_settings SET
                access_token = :access_token,
                catalog_id = :catalog_id,
                currency = :currency
             WHERE id = 1'
        );

        $stmt->execute([
            'access_token' => $settings->accessToken,
            'catalog_id' => $settings->catalogId,
            'currency' => $settings->currency,
        ]);
    }
}
