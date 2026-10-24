<?php

declare(strict_types=1);

namespace App\Domain\Settings;

interface OfferPdfSettingsRepositoryInterface
{
    public function get(): OfferPdfSettings;

    public function save(OfferPdfSettings $settings): void;
}
