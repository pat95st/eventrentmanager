<?php

declare(strict_types=1);

namespace App\Application\CaseItem\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\CaseInventoryNumber\CaseInventoryNumberRepositoryInterface;
use App\Domain\Exception\NotFoundException;

final class DeleteCaseInventoryNumberHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly CaseInventoryNumberRepositoryInterface $caseInventoryNumbers,
        // Migration 114: Fotos des Exemplars auch als Dateien entfernen.
        private readonly ?\App\Domain\CaseInventoryNumber\CaseInventoryNumberImageRepositoryInterface $images = null,
        private readonly ?\App\Infrastructure\FileStorage\LocalFileStorage $fileStorage = null,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof DeleteCaseInventoryNumber);

        $caseInventoryNumber = $this->caseInventoryNumbers->findById($command->id);
        if ($caseInventoryNumber === null) {
            throw new NotFoundException("Case-Inventurnummer \"{$command->id}\" wurde nicht gefunden.");
        }

        // Packlisten-Einträge (case_content_items) hängen per FK ON DELETE CASCADE
        // am Case-Exemplar und werden mit entfernt.
        $images = $this->images?->findByInventoryNumber($command->id) ?? [];
        $this->caseInventoryNumbers->delete($command->id);
        foreach ($images as $image) {
            try {
                $this->fileStorage?->delete('cases', $image->filePath);
            } catch (\Throwable) {
                // Datei fehlt bereits
            }
        }

        return null;
    }
}
