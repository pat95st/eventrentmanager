<?php

declare(strict_types=1);

namespace App\Application\Datev\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Datev\DatevSettings;
use App\Domain\Datev\DatevSettingsRepositoryInterface;

final class GetDatevSettingsHandler implements QueryHandlerInterface
{
    public function __construct(private readonly DatevSettingsRepositoryInterface $repository)
    {
    }

    public function handle(QueryInterface $query): DatevSettings
    {
        assert($query instanceof GetDatevSettings);

        return $this->repository->get();
    }
}
