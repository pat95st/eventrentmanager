<?php

declare(strict_types=1);

namespace App\Application\DocumentStorage\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\DocumentStorage\DocumentStorageAssignmentRepositoryInterface;

final class DeleteStorageAssignmentHandler implements CommandHandlerInterface
{
    public function __construct(private readonly DocumentStorageAssignmentRepositoryInterface $assignments)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof DeleteStorageAssignment);

        $assignment = $this->assignments->findById($command->id);
        if ($assignment === null) {
            throw new NotFoundException("Zuordnung \"{$command->id}\" wurde nicht gefunden.");
        }

        $this->assignments->delete($command->id);

        return null;
    }
}
