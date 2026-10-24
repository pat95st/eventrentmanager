<?php

declare(strict_types=1);

namespace App\Application\Settings\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Settings\ApiSettings;
use App\Domain\Settings\ApiSettingsRepositoryInterface;

final class GetApiSettingsHandler implements QueryHandlerInterface
{
    public function __construct(private readonly ApiSettingsRepositoryInterface $repository)
    {
    }

    public function handle(QueryInterface $query): ApiSettings
    {
        assert($query instanceof GetApiSettings);

        return $this->repository->get();
    }
}
