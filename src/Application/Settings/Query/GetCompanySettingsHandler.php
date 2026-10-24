<?php

declare(strict_types=1);

namespace App\Application\Settings\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Settings\CompanySettings;
use App\Domain\Settings\CompanySettingsRepositoryInterface;

final class GetCompanySettingsHandler implements QueryHandlerInterface
{
    public function __construct(private readonly CompanySettingsRepositoryInterface $settings)
    {
    }

    public function handle(QueryInterface $query): CompanySettings
    {
        assert($query instanceof GetCompanySettings);

        return $this->settings->get();
    }
}
