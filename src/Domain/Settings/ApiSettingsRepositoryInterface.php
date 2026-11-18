<?php

declare(strict_types=1);

namespace App\Domain\Settings;

use DateTimeImmutable;

interface ApiSettingsRepositoryInterface
{
    public function get(): ApiSettings;

    public function save(ApiSettings $settings): void;

    /**
     * Records (or clears, with null) the last Redis connection error without
     * touching the other settings — called from within API requests.
     */
    public function recordRedisError(?string $error, DateTimeImmutable $at): void;
}
