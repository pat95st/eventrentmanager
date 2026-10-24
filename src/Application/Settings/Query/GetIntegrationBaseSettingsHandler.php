<?php

declare(strict_types=1);

namespace App\Application\Settings\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Settings\IntegrationBaseSettings;
use App\Domain\Settings\IntegrationBaseSettingsRepositoryInterface;

final class GetIntegrationBaseSettingsHandler implements QueryHandlerInterface
{
    public function __construct(private readonly IntegrationBaseSettingsRepositoryInterface $repository)
    {
    }

    public function handle(QueryInterface $query): IntegrationBaseSettings
    {
        assert($query instanceof GetIntegrationBaseSettings);

        return $this->repository->get();
    }
}
