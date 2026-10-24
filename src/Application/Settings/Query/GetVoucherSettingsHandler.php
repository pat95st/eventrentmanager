<?php

declare(strict_types=1);

namespace App\Application\Settings\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Settings\VoucherSettings;
use App\Domain\Settings\VoucherSettingsRepositoryInterface;

final class GetVoucherSettingsHandler implements QueryHandlerInterface
{
    public function __construct(private readonly VoucherSettingsRepositoryInterface $settingsRepository)
    {
    }

    public function handle(QueryInterface $query): VoucherSettings
    {
        assert($query instanceof GetVoucherSettings);

        return $this->settingsRepository->get();
    }
}
