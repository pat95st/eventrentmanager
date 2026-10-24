<?php

declare(strict_types=1);

namespace App\Application\DocumentStorage\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\DocumentStorage\DocumentStorageTargetRepositoryInterface;

/** Löschen kaskadiert per ON DELETE CASCADE auf abhängige Zuordnungen (siehe 045_add_document_storage_assignments.sql). */
final class DeleteStorageTargetHandler implements CommandHandlerInterface
{
    public function __construct(private readonly DocumentStorageTargetRepositoryInterface $targets)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof DeleteStorageTarget);

        $target = $this->targets->findById($command->id);
        if ($target === null) {
            throw new NotFoundException("Ziellaufwerk \"{$command->id}\" wurde nicht gefunden.");
        }

        $this->targets->delete($command->id);

        return null;
    }
}
