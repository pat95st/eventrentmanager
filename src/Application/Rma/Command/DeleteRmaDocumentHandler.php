<?php

declare(strict_types=1);

namespace App\Application\Rma\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Rma\RmaDocumentRepositoryInterface;
use App\Infrastructure\FileStorage\LocalFileStorage;

final class DeleteRmaDocumentHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly RmaDocumentRepositoryInterface $rmaDocuments,
        private readonly LocalFileStorage $fileStorage,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof DeleteRmaDocument);

        $document = $this->rmaDocuments->findById($command->documentId);
        if ($document === null) {
            throw new NotFoundException("Dokument \"{$command->documentId}\" wurde nicht gefunden.");
        }

        $this->fileStorage->delete('rma/' . $document->rmaId, $document->storedFilename);
        $this->rmaDocuments->delete($document->id);

        return null;
    }
}
