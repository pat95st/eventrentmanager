<?php

declare(strict_types=1);

namespace App\Application\Settings\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Settings\GoogleMerchantSettings;
use App\Domain\Settings\GoogleMerchantSettingsRepositoryInterface;

final class GetGoogleMerchantSettingsHandler implements QueryHandlerInterface
{
    public function __construct(private readonly GoogleMerchantSettingsRepositoryInterface $repository)
    {
    }

    public function handle(QueryInterface $query): GoogleMerchantSettings
    {
        assert($query instanceof GetGoogleMerchantSettings);

        return $this->repository->get();
    }
}
