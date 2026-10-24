<?php

declare(strict_types=1);

namespace App\Application\CaseItem\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\CaseInventoryNumber\CaseInventoryNumberRepositoryInterface;
use App\Domain\Exception\NotFoundException;

final class DeleteCaseInventoryNumberHandler implements CommandHandlerInterface
{
    public function __construct(private readonly CaseInventoryNumberRepositoryInterface $caseInventoryNumbers)
    {
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
        $this->caseInventoryNumbers->delete($command->id);

        return null;
    }
}
