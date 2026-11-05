<?php

declare(strict_types=1);

namespace App\Application\Technician\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Rental\RentalRepositoryInterface;
use App\Domain\Technician\RentalTechnicianRepositoryInterface;
use App\Domain\Technician\RentalTechnicianStatus;
use DateTimeImmutable;

/**
 * Zu- oder Absage eines Technikers (Migration 134). Nur der Techniker
 * selbst; die Antwort lässt sich bis zum Ende der Veranstaltung ändern.
 * Direkt verplante Techniker können nicht absagen.
 */
final class RespondToTechnicianRequestHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly RentalTechnicianRepositoryInterface $assignments,
        private readonly RentalRepositoryInterface $rentals,
    ) {
    }

    public function handle(CommandInterface $command): string
    {
        assert($command instanceof RespondToTechnicianRequest);
        $assignment = $this->assignments->findById($command->id);
        if ($assignment === null || $assignment->userId !== $command->userId) {
            throw new NotFoundException('Die Anfrage wurde nicht gefunden.');
        }
        if ($assignment->status === RentalTechnicianStatus::Assigned) {
            throw new ValidationException(['status' => 'Du bist für diese Veranstaltung fest eingeplant. Bitte sprich Änderungen direkt ab.']);
        }
        $rental = $this->rentals->findById($assignment->rentalId);
        if ($rental !== null && $rental->endDate->format('Y-m-d') < (new DateTimeImmutable('today'))->format('Y-m-d')) {
            throw new ValidationException(['status' => 'Die Veranstaltung ist bereits vorbei.']);
        }
        $now = new DateTimeImmutable();
        $assignment->status = $command->accept ? RentalTechnicianStatus::Confirmed : RentalTechnicianStatus::Declined;
        $assignment->respondedAt = $now;
        $assignment->updatedAt = $now;
        $this->assignments->save($assignment);

        return $assignment->rentalId;
    }
}
