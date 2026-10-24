<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Settings\IntegrationBaseSettings;
use App\Domain\Settings\IntegrationBaseSettingsRepositoryInterface;
use PDO;
use RuntimeException;

final class MariadbIntegrationBaseSettingsRepository implements IntegrationBaseSettingsRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function get(): IntegrationBaseSettings
    {
        $stmt = $this->pdo->query('SELECT * FROM integration_base_settings WHERE id = 1');
        $row = $stmt->fetch();

        if ($row === false) {
            throw new RuntimeException('Integrations-Basiseinstellungen wurden nicht gefunden — fehlt die entsprechende Migration?');
        }

        return new IntegrationBaseSettings(baseUrl: $row['base_url']);
    }

    public function save(IntegrationBaseSettings $settings): void
    {
        $stmt = $this->pdo->prepare('UPDATE integration_base_settings SET base_url = :base_url WHERE id = 1');
        $stmt->execute(['base_url' => $settings->baseUrl !== null ? rtrim($settings->baseUrl, '/') : null]);
    }
}
