<?php

declare(strict_types=1);

namespace App\Application\NoticeDocument\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\NoticeDocument\NoticeDocumentArticleRepositoryInterface;
use App\Domain\NoticeDocument\NoticeDocumentRepositoryInterface;

final class UpdateNoticeDocumentAssignmentHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly NoticeDocumentRepositoryInterface $documents,
        private readonly NoticeDocumentArticleRepositoryInterface $documentArticles,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof UpdateNoticeDocumentAssignment);

        $document = $this->documents->findById($command->noticeDocumentId);
        if ($document === null) {
            throw new NotFoundException("Hinweisdokument \"{$command->noticeDocumentId}\" wurde nicht gefunden.");
        }

        $name = trim($command->name);
        if ($name === '') {
            throw new ValidationException(['name' => 'Bitte einen Namen für das Dokument angeben.']);
        }

        $document->name = $name;
        $this->documents->save($document);
        $this->documentArticles->replaceAssignments($document->id, $command->articleIds);

        return null;
    }
}
