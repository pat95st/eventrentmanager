<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Pdo;

use App\Domain\Settings\ApiSettings;
use App\Domain\Settings\ApiSettingsRepositoryInterface;
use DateTimeImmutable;
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

        return new ApiSettings(
            enabled: (bool) $row['enabled'],
            rateLimitPerMinute: (int) ($row['rate_limit_per_minute'] ?? ApiSettings::DEFAULT_RATE_LIMIT),
            rateLimitBackend: (string) ($row['rate_limit_backend'] ?? ApiSettings::BACKEND_DATABASE),
            redisConnection: (string) ($row['redis_connection'] ?? ApiSettings::REDIS_TCP),
            redisHost: (string) ($row['redis_host'] ?? '127.0.0.1'),
            redisPort: (int) ($row['redis_port'] ?? 6379),
            redisSocketPath: $row['redis_socket_path'] ?? null,
            redisUsername: $row['redis_username'] ?? null,
            redisPassword: $row['redis_password'] ?? null,
            redisDatabase: (int) ($row['redis_database'] ?? 0),
            redisKeyPrefix: (string) ($row['redis_key_prefix'] ?? 'mvl:ratelimit:'),
            redisTimeoutSeconds: ((int) ($row['redis_timeout_ms'] ?? 1000)) / 1000,
            redisLastError: $row['redis_last_error'] ?? null,
            redisLastErrorAt: isset($row['redis_last_error_at']) ? new DateTimeImmutable($row['redis_last_error_at']) : null,
        );
    }

    public function save(ApiSettings $settings): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE api_settings SET
                enabled = :enabled,
                rate_limit_per_minute = :rate_limit_per_minute,
                rate_limit_backend = :rate_limit_backend,
                redis_connection = :redis_connection,
                redis_host = :redis_host,
                redis_port = :redis_port,
                redis_socket_path = :redis_socket_path,
                redis_username = :redis_username,
                redis_password = :redis_password,
                redis_database = :redis_database,
                redis_key_prefix = :redis_key_prefix,
                redis_timeout_ms = :redis_timeout_ms
             WHERE id = 1'
        );
        $stmt->execute([
            'enabled' => $settings->enabled ? 1 : 0,
            'rate_limit_per_minute' => $settings->rateLimitPerMinute,
            'rate_limit_backend' => $settings->rateLimitBackend,
            'redis_connection' => $settings->redisConnection,
            'redis_host' => $settings->redisHost,
            'redis_port' => $settings->redisPort,
            'redis_socket_path' => $settings->redisSocketPath,
            'redis_username' => $settings->redisUsername,
            'redis_password' => $settings->redisPassword,
            'redis_database' => $settings->redisDatabase,
            'redis_key_prefix' => $settings->redisKeyPrefix,
            'redis_timeout_ms' => (int) round($settings->redisTimeoutSeconds * 1000),
        ]);
    }

    public function recordRedisError(?string $error, DateTimeImmutable $at): void
    {
        $stmt = $this->pdo->prepare('UPDATE api_settings SET redis_last_error = :error, redis_last_error_at = :at WHERE id = 1');
        $stmt->execute([
            'error' => $error !== null ? mb_substr($error, 0, 500) : null,
            'at' => $error !== null ? $at->format('Y-m-d H:i:s') : null,
        ]);
    }
}
