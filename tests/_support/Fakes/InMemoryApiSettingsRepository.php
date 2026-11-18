<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Domain\Settings\ApiSettings;
use App\Domain\Settings\ApiSettingsRepositoryInterface;
use DateTimeImmutable;

/** In-memory test double for ApiSettingsRepositoryInterface. */
final class InMemoryApiSettingsRepository implements ApiSettingsRepositoryInterface
{
    public function __construct(private ApiSettings $settings = new ApiSettings())
    {
    }

    public function get(): ApiSettings
    {
        return clone $this->settings;
    }

    public function save(ApiSettings $settings): void
    {
        $this->settings = clone $settings;
    }

    public function recordRedisError(?string $error, DateTimeImmutable $at): void
    {
        $this->settings->redisLastError = $error;
        $this->settings->redisLastErrorAt = $error !== null ? $at : null;
    }
}
