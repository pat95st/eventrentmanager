<?php

declare(strict_types=1);

namespace App\Application\ReceiptInbox\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\ReceiptInbox\ReceiptInboxAllowedSenderRepositoryInterface;

final class ListAllowedSendersHandler implements QueryHandlerInterface
{
    public function __construct(private readonly ReceiptInboxAllowedSenderRepositoryInterface $senders)
    {
    }

    public function handle(QueryInterface $query): array
    {
        assert($query instanceof ListAllowedSenders);

        return $this->senders->findAll();
    }
}
