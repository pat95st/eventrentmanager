<?php

declare(strict_types=1);

namespace App\Application\Settings\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Settings\CalDavSigningSettings;
use App\Domain\Settings\CalDavSigningSettingsRepositoryInterface;

final class GetCalDavSigningSettingsHandler implements QueryHandlerInterface
{
    public function __construct(private readonly CalDavSigningSettingsRepositoryInterface $repository)
    {
    }

    public function handle(QueryInterface $query): CalDavSigningSettings
    {
        assert($query instanceof GetCalDavSigningSettings);

        return $this->repository->get();
    }
}
