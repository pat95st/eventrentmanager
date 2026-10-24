<?php

declare(strict_types=1);

namespace App\Application\NoticeDocument\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\NoticeDocument\NoticeDocumentArticleRepositoryInterface;
use App\Domain\NoticeDocument\NoticeDocumentRepositoryInterface;
use App\Infrastructure\FileStorage\LocalFileStorage;

final class DeleteNoticeDocumentHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly NoticeDocumentRepositoryInterface $documents,
        private readonly NoticeDocumentArticleRepositoryInterface $documentArticles,
        private readonly LocalFileStorage $fileStorage,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof DeleteNoticeDocument);

        $document = $this->documents->findById($command->noticeDocumentId);
        if ($document === null) {
            throw new NotFoundException("Hinweisdokument \"{$command->noticeDocumentId}\" wurde nicht gefunden.");
        }

        $this->documentArticles->deleteByDocument($document->id);
        $this->documents->delete($document->id);
        $this->fileStorage->delete('hinweisdokumente', $document->storedFilename);

        return null;
    }
}
