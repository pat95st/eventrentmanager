<?php

declare(strict_types=1);

namespace App\Application\NoticeDocument\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\NoticeDocument\NoticeDocumentRepositoryInterface;

final class ListNoticeDocumentsHandler implements QueryHandlerInterface
{
    public function __construct(private readonly NoticeDocumentRepositoryInterface $documents)
    {
    }

    /** @return \App\Domain\NoticeDocument\NoticeDocument[] */
    public function handle(QueryInterface $query): array
    {
        assert($query instanceof ListNoticeDocuments);

        return $this->documents->findAll();
    }
}
