<?php

declare(strict_types=1);

namespace App\Application\CaseItem\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\CaseInventoryNumber\CaseInventoryNumberRepositoryInterface;
use App\Domain\Exception\NotFoundException;
use DateTimeImmutable;

final class UpdateCaseInventoryNumberHandler implements CommandHandlerInterface
{
    public function __construct(private readonly CaseInventoryNumberRepositoryInterface $caseInventoryNumbers)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof UpdateCaseInventoryNumber);

        $caseInventoryNumber = $this->caseInventoryNumbers->findById($command->id);
        if ($caseInventoryNumber === null) {
            throw new NotFoundException("Case-Inventurnummer \"{$command->id}\" wurde nicht gefunden.");
        }

        $caseInventoryNumber->acquisitionDate = $command->acquisitionDate !== null && $command->acquisitionDate !== ''
            ? new DateTimeImmutable($command->acquisitionDate)
            : null;
        $caseInventoryNumber->updatedAt = new DateTimeImmutable();

        $this->caseInventoryNumbers->save($caseInventoryNumber);

        return null;
    }
}
