<?php

declare(strict_types=1);

namespace App\Application\ReceiptInbox\Query;

use App\Application\Common\PaginatedResult;
use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\ReceiptInbox\ReceiptInboxAttachmentRepositoryInterface;
use App\Domain\ReceiptInbox\ReceiptInboxMessageRepositoryInterface;

final class ListReceiptInboxMessagesHandler implements QueryHandlerInterface
{
    public function __construct(
        private readonly ReceiptInboxMessageRepositoryInterface $messages,
        private readonly ReceiptInboxAttachmentRepositoryInterface $attachments,
    ) {
    }

    public function handle(QueryInterface $query): PaginatedResult
    {
        assert($query instanceof ListReceiptInboxMessages);

        $offset = PaginatedResult::offsetFor($query->page, $query->pageSize);
        $messages = $this->messages->findAll($query->pageSize, $offset);
        $totalCount = $this->messages->countAll();

        $attachmentsByMessage = $this->attachments->findByMessages(array_map(
            static fn ($message) => $message->id,
            $messages,
        ));

        $entries = array_map(
            fn ($message) => new ReceiptInboxMessageEntry($message, $attachmentsByMessage[$message->id] ?? []),
            $messages,
        );

        return new PaginatedResult($entries, $totalCount, $query->page, $query->pageSize);
    }
}
