<?php

declare(strict_types=1);

namespace App\Application\Settings\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Settings\OverageSettings;
use App\Domain\Settings\OverageSettingsRepositoryInterface;

final class GetOverageSettingsHandler implements QueryHandlerInterface
{
    public function __construct(private readonly OverageSettingsRepositoryInterface $settings)
    {
    }

    public function handle(QueryInterface $query): OverageSettings
    {
        assert($query instanceof GetOverageSettings);

        return $this->settings->get();
    }
}
