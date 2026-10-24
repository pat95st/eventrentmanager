<?php

declare(strict_types=1);

namespace App\Application\DocumentStorage\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\ValidationException;
use App\Domain\DocumentStorage\DocumentStorageAssignment;
use App\Domain\DocumentStorage\DocumentStorageAssignmentRepositoryInterface;
use App\Domain\DocumentStorage\DocumentStorageTargetRepositoryInterface;
use App\Domain\DocumentStorage\DocumentType;
use App\Shared\Uuid;
use DateTimeImmutable;

final class CreateStorageAssignmentHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly DocumentStorageAssignmentRepositoryInterface $assignments,
        private readonly DocumentStorageTargetRepositoryInterface $targets,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof CreateStorageAssignment);

        $errors = [];

        $documentType = DocumentType::tryFrom($command->documentType);
        if ($documentType === null) {
            $errors['documentType'] = 'Bitte einen gültigen Dokumenttyp wählen.';
        }

        $target = $this->targets->findById($command->storageTargetId);
        if ($target === null) {
            $errors['storageTargetId'] = 'Bitte ein gültiges Ziellaufwerk wählen.';
        }

        $targetPath = trim($command->targetPath);
        if ($targetPath === '') {
            $errors['targetPath'] = 'Bitte einen Ablagepfad angeben.';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $assignment = new DocumentStorageAssignment(
            id: Uuid::v4(),
            documentType: $documentType,
            storageTargetId: $command->storageTargetId,
            targetPath: $targetPath,
            enabled: $command->enabled,
            createdAt: new DateTimeImmutable(),
        );

        $this->assignments->save($assignment);

        return $assignment->id;
    }
}
