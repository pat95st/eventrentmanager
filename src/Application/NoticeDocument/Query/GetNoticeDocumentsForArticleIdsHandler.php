<?php

declare(strict_types=1);

namespace App\Application\NoticeDocument\Query;

use App\Application\Query\QueryHandlerInterface;
use App\Application\Query\QueryInterface;
use App\Domain\NoticeDocument\NoticeDocumentArticleRepositoryInterface;
use App\Domain\NoticeDocument\NoticeDocumentRepositoryInterface;

/**
 * Ermittelt, welche Hinweisdokumente für die übergebenen Artikel hinterlegt
 * sind — genutzt auf der Veranstaltungs-Detailseite, um dem Benutzer
 * anzuzeigen/zu ermöglichen, diese Dokumente an den Kunden zu verschicken
 * (siehe RentalController, SendNoticeDocumentsToCustomerHandler).
 */
final class GetNoticeDocumentsForArticleIdsHandler implements QueryHandlerInterface
{
    public function __construct(
        private readonly NoticeDocumentArticleRepositoryInterface $documentArticles,
        private readonly NoticeDocumentRepositoryInterface $documents,
    ) {
    }

    /** @return \App\Domain\NoticeDocument\NoticeDocument[] */
    public function handle(QueryInterface $query): array
    {
        assert($query instanceof GetNoticeDocumentsForArticleIds);

        if ($query->articleIds === []) {
            return [];
        }

        $documentIds = $this->documentArticles->findDocumentIdsByArticleIds($query->articleIds);

        return $this->documents->findByIds($documentIds);
    }
}
