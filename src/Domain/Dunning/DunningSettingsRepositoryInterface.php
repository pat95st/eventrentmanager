<?php

declare(strict_types=1);

namespace App\Domain\Dunning;

interface DunningSettingsRepositoryInterface
{
    public function get(): DunningSettings;

    public function save(DunningSettings $settings): void;
}
