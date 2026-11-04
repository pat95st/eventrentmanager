<?php

declare(strict_types=1);

namespace App\Application\Settings\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Settings\SecuritySettings;
use App\Domain\Settings\SecuritySettingsRepositoryInterface;

final class GetSecuritySettingsHandler implements QueryHandlerInterface
{
    public function __construct(private readonly SecuritySettingsRepositoryInterface $settings)
    {
    }

    public function handle(QueryInterface $query): SecuritySettings
    {
        assert($query instanceof GetSecuritySettings);

        return $this->settings->get();
    }
}
