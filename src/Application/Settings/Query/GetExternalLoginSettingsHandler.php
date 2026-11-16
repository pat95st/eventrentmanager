<?php

declare(strict_types=1);

namespace App\Application\Settings\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Settings\ExternalLoginSettings;
use App\Domain\Settings\ExternalLoginSettingsRepositoryInterface;

final class GetExternalLoginSettingsHandler implements QueryHandlerInterface
{
    public function __construct(private readonly ExternalLoginSettingsRepositoryInterface $settings)
    {
    }

    public function handle(QueryInterface $query): ExternalLoginSettings
    {
        assert($query instanceof GetExternalLoginSettings);

        return $this->settings->get();
    }
}
