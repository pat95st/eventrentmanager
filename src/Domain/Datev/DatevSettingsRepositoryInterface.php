<?php

declare(strict_types=1);

namespace App\Domain\Datev;

interface DatevSettingsRepositoryInterface
{
    public function get(): DatevSettings;

    public function save(DatevSettings $settings): void;
}
