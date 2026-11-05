<?php

declare(strict_types=1);

namespace App\Application\Technician\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Technician\RentalTechnicianRepositoryInterface;
use App\Domain\Technician\TradeRepositoryInterface;
use DateTimeImmutable;

final class UpdateRentalTechnicianHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly RentalTechnicianRepositoryInterface $assignments,
        private readonly TradeRepositoryInterface $trades,
    ) {
    }

    public function handle(CommandInterface $command): string
    {
        assert($command instanceof UpdateRentalTechnician);
        $assignment = $this->assignments->findById($command->id) ?? throw new NotFoundException('Die Zuordnung wurde nicht gefunden.');
        $known = array_map(static fn ($t) => $t->id, $this->trades->findAll());
        $assignment->tradeIds = array_values(array_intersect(array_map('strval', $command->tradeIds), $known));
        $note = trim((string) $command->note);
        $assignment->note = $note === '' ? null : mb_substr($note, 0, 500);
        $assignment->updatedAt = new DateTimeImmutable();
        $this->assignments->save($assignment);

        return $assignment->rentalId;
    }
}
