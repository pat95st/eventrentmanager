<?php

declare(strict_types=1);

namespace App\Application\Rma\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Rma\RmaPackagePhotoRepositoryInterface;
use App\Infrastructure\FileStorage\LocalFileStorage;

final class DeleteRmaPackagePhotoHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly RmaPackagePhotoRepositoryInterface $rmaPackagePhotos,
        private readonly LocalFileStorage $fileStorage,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof DeleteRmaPackagePhoto);

        $photo = $this->rmaPackagePhotos->findById($command->photoId);
        if ($photo === null) {
            throw new NotFoundException("Foto \"{$command->photoId}\" wurde nicht gefunden.");
        }

        $this->fileStorage->delete('rma-versandfotos/' . $photo->rmaId, $photo->storedFilename);
        $this->rmaPackagePhotos->delete($photo->id);

        return null;
    }
}
