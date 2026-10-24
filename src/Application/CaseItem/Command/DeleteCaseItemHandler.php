<?php

declare(strict_types=1);

namespace App\Application\CaseItem\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\CaseItem\CaseItemRepositoryInterface;
use App\Domain\Exception\NotFoundException;

final class DeleteCaseItemHandler implements CommandHandlerInterface
{
    public function __construct(private readonly CaseItemRepositoryInterface $cases)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof DeleteCaseItem);

        $caseItem = $this->cases->findById($command->id);
        if ($caseItem === null) {
            throw new NotFoundException("Case \"{$command->id}\" wurde nicht gefunden.");
        }

        // Case-Exemplare (Inventurnummern) und deren Packlisten-Einträge hängen per
        // FK ON DELETE CASCADE an case_items/case_inventory_numbers und werden von
        // der Datenbank mit entfernt (keine Dateien betroffen, anders als bei Artikeln).
        $this->cases->delete($command->id);

        return null;
    }
}
