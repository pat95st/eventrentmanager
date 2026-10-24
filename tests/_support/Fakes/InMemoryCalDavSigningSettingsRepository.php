<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Domain\Settings\CalDavSigningSettings;
use App\Domain\Settings\CalDavSigningSettingsRepositoryInterface;

/** In-Memory-Test-Double für CalDavSigningSettingsRepositoryInterface, siehe InMemoryCalendarRepository. */
final class InMemoryCalDavSigningSettingsRepository implements CalDavSigningSettingsRepositoryInterface
{
    private CalDavSigningSettings $settings;

    public function __construct()
    {
        $this->settings = new CalDavSigningSettings(null, null, false, null);
    }

    public function get(): CalDavSigningSettings
    {
        return $this->settings;
    }

    public function save(CalDavSigningSettings $settings): void
    {
        $this->settings = $settings;
    }
}
