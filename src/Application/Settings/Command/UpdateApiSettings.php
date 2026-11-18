<?php

declare(strict_types=1);

namespace App\Application\Settings\Command;

use App\Application\Command\CommandInterface;

/**
 * Saves the API settings page. Form values arrive as raw strings and are
 * validated by the handler. $redisPassword: null/empty keeps the stored
 * password (it is never sent back to the form), $clearRedisPassword removes it.
 */
final class UpdateApiSettings implements CommandInterface
{
    public function __construct(
        public readonly bool $enabled,
        public readonly ?string $rateLimitPerMinute = null,
        public readonly ?string $rateLimitBackend = null,
        public readonly ?string $redisConnection = null,
        public readonly ?string $redisHost = null,
        public readonly ?string $redisPort = null,
        public readonly ?string $redisSocketPath = null,
        public readonly ?string $redisUsername = null,
        public readonly ?string $redisPassword = null,
        public readonly bool $clearRedisPassword = false,
        public readonly ?string $redisDatabase = null,
        public readonly ?string $redisKeyPrefix = null,
        public readonly ?string $redisTimeoutMs = null,
    ) {
    }
}
