<?php

declare(strict_types=1);

namespace App\Application\Settings\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Settings\PricingSettings;
use App\Domain\Settings\PricingSettingsRepositoryInterface;

final class GetPricingSettingsHandler implements QueryHandlerInterface
{
    public function __construct(private readonly PricingSettingsRepositoryInterface $settings)
    {
    }

    public function handle(QueryInterface $query): PricingSettings
    {
        assert($query instanceof GetPricingSettings);

        return $this->settings->get();
    }
}
