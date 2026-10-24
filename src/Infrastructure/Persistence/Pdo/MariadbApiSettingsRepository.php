<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Settings\ApiSettings;
use App\Domain\Settings\ApiSettingsRepositoryInterface;
use PDO;
use RuntimeException;

final class MariadbApiSettingsRepository implements ApiSettingsRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function get(): ApiSettings
    {
        $stmt = $this->pdo->query('SELECT * FROM api_settings WHERE id = 1');
        $row = $stmt->fetch();

        if ($row === false) {
            throw new RuntimeException('API-Einstellungen wurden nicht gefunden — fehlt die entsprechende Migration?');
        }

        return new ApiSettings(enabled: (bool) $row['enabled']);
    }

    public function save(ApiSettings $settings): void
    {
        $stmt = $this->pdo->prepare('UPDATE api_settings SET enabled = :enabled WHERE id = 1');
        $stmt->execute(['enabled' => $settings->enabled ? 1 : 0]);
    }
}
