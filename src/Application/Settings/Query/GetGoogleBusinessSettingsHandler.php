<?php

declare(strict_types=1);

namespace App\Application\Settings\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Settings\GoogleBusinessSettings;
use App\Domain\Settings\GoogleBusinessSettingsRepositoryInterface;

final class GetGoogleBusinessSettingsHandler implements QueryHandlerInterface
{
    public function __construct(private readonly GoogleBusinessSettingsRepositoryInterface $repository)
    {
    }

    public function handle(QueryInterface $query): GoogleBusinessSettings
    {
        assert($query instanceof GetGoogleBusinessSettings);

        return $this->repository->get();
    }
}
