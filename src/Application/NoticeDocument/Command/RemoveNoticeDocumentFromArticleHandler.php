<?php

declare(strict_types=1);

namespace App\Application\NoticeDocument\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\NoticeDocument\NoticeDocumentArticleRepositoryInterface;
use App\Domain\NoticeDocument\NoticeDocumentRepositoryInterface;

/**
 * Entfernt die Zuordnung eines Hinweisdokuments zu EINEM Artikel (z. B.
 * "von diesem Artikel entfernen" direkt auf der Artikel-Detailseite) —
 * anders als DeleteNoticeDocument bleibt das Dokument selbst (und etwaige
 * Zuordnungen zu anderen Artikeln) dabei erhalten.
 */
final class RemoveNoticeDocumentFromArticleHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly NoticeDocumentRepositoryInterface $documents,
        private readonly NoticeDocumentArticleRepositoryInterface $documentArticles,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof RemoveNoticeDocumentFromArticle);

        $document = $this->documents->findById($command->noticeDocumentId);
        if ($document === null) {
            throw new NotFoundException("Hinweisdokument \"{$command->noticeDocumentId}\" wurde nicht gefunden.");
        }

        $this->documentArticles->removeAssignment($command->noticeDocumentId, $command->articleId);

        return null;
    }
}
