<?php

declare(strict_types=1);

namespace App\Application\Dunning\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Dunning\DunningSettings;
use App\Domain\Dunning\DunningSettingsRepositoryInterface;

final class GetDunningSettingsHandler implements QueryHandlerInterface
{
    public function __construct(private readonly DunningSettingsRepositoryInterface $dunningSettings)
    {
    }

    public function handle(QueryInterface $query): DunningSettings
    {
        assert($query instanceof GetDunningSettings);

        return $this->dunningSettings->get();
    }
}
