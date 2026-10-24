<?php

declare(strict_types=1);

namespace App\Domain\Settings;

interface PdfDesignSettingsRepositoryInterface
{
    public function get(): PdfDesignSettings;

    public function save(PdfDesignSettings $settings): void;
}
