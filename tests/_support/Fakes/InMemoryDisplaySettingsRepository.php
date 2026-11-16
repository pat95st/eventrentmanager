<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Domain\Display\DisplaySettings;
use App\Domain\Display\DisplaySettingsRepositoryInterface;

final class InMemoryDisplaySettingsRepository implements DisplaySettingsRepositoryInterface
{
    private DisplaySettings $settings;

    public function __construct()
    {
        $this->settings = new DisplaySettings();
    }

    public function get(): DisplaySettings
    {
        return clone $this->settings;
    }

    public function save(DisplaySettings $settings): void
    {
        $this->settings = clone $settings;
    }
}
