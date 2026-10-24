<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Settings\PricingSettings;
use App\Domain\Settings\PricingSettingsRepositoryInterface;
use PDO;
use RuntimeException;

final class MariadbPricingSettingsRepository implements PricingSettingsRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function get(): PricingSettings
    {
        $stmt = $this->pdo->query('SELECT * FROM pricing_settings WHERE id = 1');
        $row = $stmt->fetch();

        if ($row === false) {
            // Sollte durch die Migration/das Schema nie vorkommen (die
            // Singleton-Zeile wird dort direkt mit angelegt), aber ein
            // klarer Fehler ist hier besser als eine stille Falscheinstellung.
            throw new RuntimeException('Preis-Einstellungen wurden nicht gefunden — fehlt die Migration 037?');
        }

        return new PricingSettings(
            accessoryFlatRatePercent: $row['accessory_flat_rate_percent'],
        );
    }

    public function save(PricingSettings $settings): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE pricing_settings SET accessory_flat_rate_percent = :accessory_flat_rate_percent WHERE id = 1'
        );

        $stmt->execute([
            'accessory_flat_rate_percent' => $settings->accessoryFlatRatePercent,
        ]);
    }
}
