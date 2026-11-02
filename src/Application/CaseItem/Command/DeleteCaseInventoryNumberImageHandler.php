<?php

declare(strict_types=1);

namespace App\Application\CaseItem\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\CaseInventoryNumber\CaseInventoryNumberImageRepositoryInterface;
use App\Domain\Exception\NotFoundException;
use App\Infrastructure\FileStorage\LocalFileStorage;

final class DeleteCaseInventoryNumberImageHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly CaseInventoryNumberImageRepositoryInterface $images,
        private readonly LocalFileStorage $fileStorage,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof DeleteCaseInventoryNumberImage);

        $image = $this->images->findById($command->imageId);
        if ($image === null) {
            throw new NotFoundException('Bild wurde nicht gefunden.');
        }

        $this->images->delete($image->id);
        try {
            $this->fileStorage->delete('cases', $image->filePath);
        } catch (\Throwable) {
            // Datei fehlt bereits — Datensatz ist entfernt, das genügt.
        }

        return null;
    }
}
