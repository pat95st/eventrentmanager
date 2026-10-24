<?php

declare(strict_types=1);

namespace App\Application\Push\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Push\PushSubscriptionRepositoryInterface;

final class ListPushSubscriptionsForUserHandler implements QueryHandlerInterface
{
    public function __construct(private readonly PushSubscriptionRepositoryInterface $subscriptions)
    {
    }

    public function handle(QueryInterface $query): mixed
    {
        assert($query instanceof ListPushSubscriptionsForUser);

        return $this->subscriptions->findAllForUser($query->userId);
    }
}
