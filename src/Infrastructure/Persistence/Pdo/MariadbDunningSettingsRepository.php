<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Dunning\DunningSettings;
use App\Domain\Dunning\DunningSettingsRepositoryInterface;
use PDO;

final class MariadbDunningSettingsRepository implements DunningSettingsRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function get(): DunningSettings
    {
        $stmt = $this->pdo->query('SELECT * FROM dunning_settings WHERE id = 1');
        $row = $stmt->fetch();

        if (!$row) {
            return new DunningSettings();
        }

        return new DunningSettings(autoSendEnabled: (bool) $row['auto_send_enabled']);
    }

    public function save(DunningSettings $settings): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO dunning_settings (id, auto_send_enabled) VALUES (1, :auto_send_enabled)
             ON DUPLICATE KEY UPDATE auto_send_enabled = :auto_send_enabled2'
        );

        $stmt->execute([
            'auto_send_enabled' => $settings->autoSendEnabled ? 1 : 0,
            'auto_send_enabled2' => $settings->autoSendEnabled ? 1 : 0,
        ]);
    }
}
