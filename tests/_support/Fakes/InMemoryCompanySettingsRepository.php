<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Domain\Settings\CompanySettings;
use App\Domain\Settings\CompanySettingsRepositoryInterface;

/** In-Memory-Test-Double für CompanySettingsRepositoryInterface, siehe InMemoryCalendarRepository. */
final class InMemoryCompanySettingsRepository implements CompanySettingsRepositoryInterface
{
    public function __construct(private CompanySettings $settings = new CompanySettings(
        name: null,
        street: null,
        postalCode: null,
        city: null,
        country: null,
        logoFilename: null,
    )) {
    }

    public function get(): CompanySettings
    {
        return $this->settings;
    }

    public function save(CompanySettings $settings): void
    {
        $this->settings = $settings;
    }
}
