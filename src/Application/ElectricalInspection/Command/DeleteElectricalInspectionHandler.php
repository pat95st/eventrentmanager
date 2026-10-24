<?php

declare(strict_types=1);

namespace App\Application\ElectricalInspection\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\ElectricalInspection\ElectricalInspectionRepositoryInterface;
use App\Domain\Exception\NotFoundException;

final class DeleteElectricalInspectionHandler implements CommandHandlerInterface
{
    public function __construct(private readonly ElectricalInspectionRepositoryInterface $inspections)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof DeleteElectricalInspection);

        $inspection = $this->inspections->findById($command->id);
        if ($inspection === null) {
            throw new NotFoundException("Elektroprüfung \"{$command->id}\" wurde nicht gefunden.");
        }

        $this->inspections->delete($command->id);

        return null;
    }
}
