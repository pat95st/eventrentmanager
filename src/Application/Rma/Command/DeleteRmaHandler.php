<?php

declare(strict_types=1);

namespace App\Application\Rma\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Rma\RmaDocumentRepositoryInterface;
use App\Domain\Rma\RmaPackagePhotoRepositoryInterface;
use App\Domain\Rma\RmaRepositoryInterface;
use App\Infrastructure\FileStorage\LocalFileStorage;

final class DeleteRmaHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly RmaRepositoryInterface $rmas,
        private readonly RmaDocumentRepositoryInterface $rmaDocuments,
        private readonly RmaPackagePhotoRepositoryInterface $rmaPackagePhotos,
        private readonly LocalFileStorage $fileStorage,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof DeleteRma);

        $rma = $this->rmas->findById($command->id);
        if ($rma === null) {
            throw new NotFoundException("RMA-Vorgang \"{$command->id}\" wurde nicht gefunden.");
        }

        // Dokumente/Versandfotos liegen als Dateien auf der Platte und werden
        // nicht durch die DB-FK kaskadiert entfernt — das übernehmen wir hier
        // explizit, bevor der DB-Datensatz (und damit die rma_documents-/
        // rma_package_photos-Zeilen per ON DELETE CASCADE) gelöscht wird.
        foreach ($this->rmaDocuments->findByRma($rma->id) as $document) {
            $this->fileStorage->delete('rma/' . $rma->id, $document->storedFilename);
        }
        foreach ($this->rmaPackagePhotos->findByRma($rma->id) as $photo) {
            $this->fileStorage->delete('rma-versandfotos/' . $rma->id, $photo->storedFilename);
        }

        $this->rmas->delete($command->id);

        return null;
    }
}
