<?php

declare(strict_types=1);

namespace App\Application\Settings\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Push\PushSettingsRepositoryInterface;

final class GetPushSettingsHandler implements QueryHandlerInterface
{
    public function __construct(private readonly PushSettingsRepositoryInterface $settings)
    {
    }

    public function handle(QueryInterface $query): mixed
    {
        assert($query instanceof GetPushSettings);

        return $this->settings->get();
    }
}
