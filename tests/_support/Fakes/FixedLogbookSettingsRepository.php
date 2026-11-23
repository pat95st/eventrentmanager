<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Domain\Logbook\LogbookSettings;
use App\Domain\Logbook\LogbookSettingsRepositoryInterface;

/** In-memory test double of LogbookSettingsRepositoryInterface. */
final class FixedLogbookSettingsRepository implements LogbookSettingsRepositoryInterface
{
    public function __construct(public LogbookSettings $settings = new LogbookSettings())
    {
    }

    public function get(): LogbookSettings
    {
        return $this->settings;
    }

    public function save(LogbookSettings $settings): void
    {
        $this->settings = $settings;
    }
}
