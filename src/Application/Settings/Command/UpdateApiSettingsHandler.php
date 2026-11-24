<?php

declare(strict_types=1);

namespace App\Application\Settings\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\ValidationException;
use App\Domain\Settings\ApiSettings;
use App\Domain\Settings\ApiSettingsRepositoryInterface;

final class UpdateApiSettingsHandler implements CommandHandlerInterface
{
    public function __construct(private readonly ApiSettingsRepositoryInterface $repository)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof UpdateApiSettings);

        $current = $this->repository->get();
        $errors = [];

        $rateLimit = self::int($command->rateLimitPerMinute, $current->rateLimitPerMinute, 0, 1_000_000, 'rateLimitPerMinute', 'Bitte eine ganze Zahl ≥ 0 angeben (0 = unbegrenzt).', $errors);

        $backend = $command->rateLimitBackend ?? $current->rateLimitBackend;
        if (!in_array($backend, [ApiSettings::BACKEND_DATABASE, ApiSettings::BACKEND_REDIS], true)) {
            $errors['rateLimitBackend'] = 'Unbekannte Zählweise.';
        }
        $connection = $command->redisConnection ?? $current->redisConnection;
        if (!in_array($connection, [ApiSettings::REDIS_TCP, ApiSettings::REDIS_TLS, ApiSettings::REDIS_SOCKET], true)) {
            $errors['redisConnection'] = 'Unbekannte Verbindungsart.';
        }

        $host = $command->redisHost !== null ? trim($command->redisHost) : $current->redisHost;
        $port = self::int($command->redisPort, $current->redisPort, 1, 65535, 'redisPort', 'Bitte einen Port zwischen 1 und 65535 angeben.', $errors);
        $socketPath = $command->redisSocketPath !== null ? (trim($command->redisSocketPath) !== '' ? trim($command->redisSocketPath) : null) : $current->redisSocketPath;
        $username = $command->redisUsername !== null ? (trim($command->redisUsername) !== '' ? trim($command->redisUsername) : null) : $current->redisUsername;
        $database = self::int($command->redisDatabase, $current->redisDatabase, 0, 1000, 'redisDatabase', 'Bitte eine Datenbank-Nummer ≥ 0 angeben.', $errors);
        $prefix = $command->redisKeyPrefix !== null ? trim($command->redisKeyPrefix) : $current->redisKeyPrefix;
        if ($prefix === '' || mb_strlen($prefix) > 100 || preg_match('/\s/', $prefix) === 1) {
            $errors['redisKeyPrefix'] = 'Bitte ein Schlüssel-Präfix ohne Leerzeichen angeben (max. 100 Zeichen).';
        }
        $timeoutMs = self::int($command->redisTimeoutMs, (int) round($current->redisTimeoutSeconds * 1000), 50, 30000, 'redisTimeoutMs', 'Bitte einen Timeout zwischen 50 und 30000 ms angeben.', $errors);

        $password = $current->redisPassword;
        if ($command->clearRedisPassword) {
            $password = null;
        } elseif ($command->redisPassword !== null && $command->redisPassword !== '') {
            $password = $command->redisPassword;
        }

        if ($backend === ApiSettings::BACKEND_REDIS) {
            if ($connection === ApiSettings::REDIS_SOCKET && $socketPath === null) {
                $errors['redisSocketPath'] = 'Bitte den Pfad zum Redis-Socket angeben (z. B. /run/redis/redis.sock).';
            }
            if ($connection !== ApiSettings::REDIS_SOCKET && ($host === '' || preg_match('/^[A-Za-z0-9.\-:\[\]]+$/', $host) !== 1)) {
                $errors['redisHost'] = 'Bitte einen gültigen Hostnamen oder eine IP-Adresse angeben.';
            }
        }

        if ($command->mcpEnabled && !$command->enabled) {
            $errors['mcpEnabled'] = 'Der MCP-Server nutzt Tokens, Ratenbegrenzung und Protokoll der REST-API — bitte dafür auch die API aktivieren.';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $this->repository->save(new ApiSettings(
            enabled: $command->enabled,
            rateLimitPerMinute: $rateLimit,
            rateLimitBackend: $backend,
            redisConnection: $connection,
            redisHost: $host !== '' ? $host : '127.0.0.1',
            redisPort: $port,
            redisSocketPath: $socketPath,
            redisUsername: $username,
            redisPassword: $password,
            redisDatabase: $database,
            redisKeyPrefix: $prefix,
            redisTimeoutSeconds: $timeoutMs / 1000,
            redisLastError: $current->redisLastError,
            redisLastErrorAt: $current->redisLastErrorAt,
            mcpEnabled: $command->mcpEnabled,
            mcpReadOnly: $command->mcpReadOnly,
        ));

        return null;
    }

    /** @param array<string, string> $errors */
    private static function int(?string $raw, int $default, int $min, int $max, string $field, string $message, array &$errors): int
    {
        if ($raw === null) {
            return $default;
        }
        $raw = trim($raw);
        if (preg_match('/^\d+$/', $raw) !== 1 || (int) $raw < $min || (int) $raw > $max) {
            $errors[$field] = $message;

            return $default;
        }

        return (int) $raw;
    }
}
