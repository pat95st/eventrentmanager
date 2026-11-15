<?php

declare(strict_types=1);

namespace App\Application\OnlinePayment\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\OnlinePayment\OnlinePaymentSettings;
use App\Domain\OnlinePayment\OnlinePaymentSettingsRepositoryInterface;

final class GetOnlinePaymentSettingsHandler implements QueryHandlerInterface
{
    public function __construct(private readonly OnlinePaymentSettingsRepositoryInterface $repository)
    {
    }

    public function handle(QueryInterface $query): OnlinePaymentSettings
    {
        assert($query instanceof GetOnlinePaymentSettings);

        return $this->repository->get();
    }
}
