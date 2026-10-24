<?php

declare(strict_types=1);

namespace App\Application\CaseItem\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\CaseContent\CaseContentItemRepositoryInterface;
use App\Domain\Exception\NotFoundException;

final class DeleteCaseContentItemHandler implements CommandHandlerInterface
{
    public function __construct(private readonly CaseContentItemRepositoryInterface $contentItems)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof DeleteCaseContentItem);

        $item = $this->contentItems->findById($command->id);
        if ($item === null) {
            throw new NotFoundException("Packlisten-Eintrag \"{$command->id}\" wurde nicht gefunden.");
        }

        $this->contentItems->delete($command->id);

        return null;
    }
}
