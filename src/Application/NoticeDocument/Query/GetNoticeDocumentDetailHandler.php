<?php

declare(strict_types=1);

namespace App\Application\NoticeDocument\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\NoticeDocument\NoticeDocumentArticleRepositoryInterface;
use App\Domain\NoticeDocument\NoticeDocumentRepositoryInterface;

final class GetNoticeDocumentDetailHandler implements QueryHandlerInterface
{
    public function __construct(
        private readonly NoticeDocumentRepositoryInterface $documents,
        private readonly NoticeDocumentArticleRepositoryInterface $documentArticles,
    ) {
    }

    public function handle(QueryInterface $query): NoticeDocumentDetail
    {
        assert($query instanceof GetNoticeDocumentDetail);

        $document = $this->documents->findById($query->noticeDocumentId);
        if ($document === null) {
            throw new NotFoundException("Hinweisdokument \"{$query->noticeDocumentId}\" wurde nicht gefunden.");
        }

        return new NoticeDocumentDetail($document, $this->documentArticles->findArticleIdsByDocument($document->id));
    }
}
