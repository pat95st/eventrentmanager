<?php

declare(strict_types=1);

namespace App\Application\Messaging\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Messaging\MessagingSettingsRepositoryInterface;

final class GetMessagingSettingsHandler implements QueryHandlerInterface
{
    public function __construct(private readonly MessagingSettingsRepositoryInterface $settings)
    {
    }

    public function handle(QueryInterface $query): mixed
    {
        assert($query instanceof GetMessagingSettings);

        return $this->settings->get();
    }
}
