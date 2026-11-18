<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Routing\RoutingSettings;
use App\Domain\Routing\RoutingSettingsRepositoryInterface;
use PDO;

final class MariadbRoutingSettingsRepository implements RoutingSettingsRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function get(): RoutingSettings
    {
        $row = $this->pdo->query('SELECT api_key, buffer_minutes FROM routing_settings WHERE id = 1')->fetch(PDO::FETCH_ASSOC);

        return $row ? new RoutingSettings($row['api_key'], (int) $row['buffer_minutes']) : new RoutingSettings();
    }

    public function save(RoutingSettings $settings): void
    {
        $this->pdo->prepare(
            'INSERT INTO routing_settings (id, api_key, buffer_minutes) VALUES (1, :key, :buffer)
             ON DUPLICATE KEY UPDATE api_key = VALUES(api_key), buffer_minutes = VALUES(buffer_minutes)'
        )->execute(['key' => $settings->apiKey, 'buffer' => $settings->bufferMinutes]);
    }
}
