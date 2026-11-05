<?php

declare(strict_types=1);

namespace App\Application\Technician\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Technician\RentalTechnicianRepositoryInterface;

final class RemoveRentalTechnicianHandler implements CommandHandlerInterface
{
    public function __construct(private readonly RentalTechnicianRepositoryInterface $assignments)
    {
    }

    public function handle(CommandInterface $command): string
    {
        assert($command instanceof RemoveRentalTechnician);
        $assignment = $this->assignments->findById($command->id) ?? throw new NotFoundException('Die Zuordnung wurde nicht gefunden.');
        $this->assignments->delete($assignment->id);

        return $assignment->rentalId;
    }
}
