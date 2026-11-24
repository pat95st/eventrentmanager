<?php

declare(strict_types=1);

namespace App\Domain\Settings;

use DateTimeImmutable;

/**
 * Global settings of the REST API (/api/v1): master switch plus rate
 * limiting (migration 175). The rate limit counter lives either in the
 * database or in Redis; if Redis is configured but unreachable, the API
 * falls back to the database counter and records the error here so the
 * settings page can show it.
 *
 * The MCP server (/mcp) shares tokens, rate limiting and the request log
 * with the REST API and has its own master and read-only switches.
 */
final class ApiSettings
{
    public const BACKEND_DATABASE = 'database';
    public const BACKEND_REDIS = 'redis';
    public const REDIS_TCP = 'tcp';
    public const REDIS_TLS = 'tls';
    public const REDIS_SOCKET = 'socket';
    public const DEFAULT_RATE_LIMIT = 600;

    public function __construct(
        public bool $enabled = false,
        // Requests per minute and token; 0 = unlimited.
        public int $rateLimitPerMinute = self::DEFAULT_RATE_LIMIT,
        public string $rateLimitBackend = self::BACKEND_DATABASE,
        public string $redisConnection = self::REDIS_TCP,
        public string $redisHost = '127.0.0.1',
        public int $redisPort = 6379,
        public ?string $redisSocketPath = null,
        public ?string $redisUsername = null,
        public ?string $redisPassword = null,
        public int $redisDatabase = 0,
        public string $redisKeyPrefix = 'mvl:ratelimit:',
        public float $redisTimeoutSeconds = 1.0,
        public ?string $redisLastError = null,
        public ?DateTimeImmutable $redisLastErrorAt = null,
        // MCP server at /mcp (migration 188). Requires $enabled; while off,
        // /mcp answers 404 like the REST API does when it is switched off.
        public bool $mcpEnabled = false,
        // Global read-only switch for MCP: no write tool is offered to any
        // token, whatever its scopes (on top of the token's own read-only flag).
        public bool $mcpReadOnly = false,
    ) {
    }

    /** MCP is reachable only while both the REST API and MCP are switched on. */
    public function mcpAvailable(): bool
    {
        return $this->enabled && $this->mcpEnabled;
    }

    public function usesRedis(): bool
    {
        return $this->rateLimitBackend === self::BACKEND_REDIS;
    }
}
