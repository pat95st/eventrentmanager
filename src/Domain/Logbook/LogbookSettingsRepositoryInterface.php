<?php

declare(strict_types=1);

namespace App\Domain\Logbook;

interface LogbookSettingsRepositoryInterface
{
    public function get(): LogbookSettings;

    public function save(LogbookSettings $settings): void;
}
