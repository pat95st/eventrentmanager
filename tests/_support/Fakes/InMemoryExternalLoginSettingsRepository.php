<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Domain\Settings\ExternalLoginSettings;
use App\Domain\Settings\ExternalLoginSettingsRepositoryInterface;

/** In-memory test double for ExternalLoginSettingsRepositoryInterface (see tests/README.md). */
final class InMemoryExternalLoginSettingsRepository implements ExternalLoginSettingsRepositoryInterface
{
    public function __construct(public ExternalLoginSettings $settings = new ExternalLoginSettings())
    {
    }

    public function get(): ExternalLoginSettings
    {
        return $this->settings;
    }

    public function save(ExternalLoginSettings $settings): void
    {
        $this->settings = $settings;
    }
}
