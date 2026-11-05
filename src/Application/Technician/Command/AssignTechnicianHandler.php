<?php

declare(strict_types=1);

namespace App\Application\Technician\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Application\Technician\TechnicianNotifier;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\Rental\RentalRepositoryInterface;
use App\Domain\Technician\RentalTechnician;
use App\Domain\Technician\RentalTechnicianRepositoryInterface;
use App\Domain\Technician\RentalTechnicianStatus;
use App\Domain\Technician\TechnicianAccess;
use App\Domain\Technician\TechnicianProfileRepositoryInterface;
use App\Domain\Technician\TradeRepositoryInterface;
use App\Domain\User\UserRepositoryInterface;
use App\Shared\Uuid;
use DateTimeImmutable;

/**
 * Techniker zu einer Veranstaltung hinzufügen (Migration 134): muss er
 * zustimmen, wird er angefragt (Status requested), sonst direkt eingeplant
 * (assigned). In beiden Fällen geht eine Benachrichtigung mit
 * Termineinladung raus. Wer schon abgesagt hatte, wird erneut angefragt.
 *
 * @return array{id: string, status: RentalTechnicianStatus, notification: array{email: bool, channels: string[], errors: string[]}|null}
 */
final class AssignTechnicianHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly RentalTechnicianRepositoryInterface $assignments,
        private readonly RentalRepositoryInterface $rentals,
        private readonly UserRepositoryInterface $users,
        private readonly TechnicianProfileRepositoryInterface $profiles,
        private readonly TradeRepositoryInterface $trades,
        private readonly ?TechnicianNotifier $notifier = null,
    ) {
    }

    public function handle(CommandInterface $command): array
    {
        assert($command instanceof AssignTechnician);
        if ($this->rentals->findById($command->rentalId) === null) {
            throw new NotFoundException('Die Veranstaltung wurde nicht gefunden.');
        }
        $user = $this->users->findById($command->userId);
        if ($user === null || !$user->isActive || !TechnicianAccess::isTechnician($user)) {
            throw new ValidationException(['userId' => 'Bitte einen aktiven Benutzer mit dem Recht „Techniker“ auswählen.']);
        }
        $known = array_map(static fn ($t) => $t->id, $this->trades->findAll());
        $tradeIds = array_values(array_intersect(array_map('strval', $command->tradeIds), $known));
        $note = trim((string) $command->note);
        $note = $note === '' ? null : mb_substr($note, 0, 500);

        $profile = $this->profiles->find($user->id);
        $requiresConfirmation = $profile?->requiresConfirmation ?? true;
        $now = new DateTimeImmutable();
        $existing = $this->assignments->findByRentalAndUser($command->rentalId, $user->id);
        $notify = true;

        if ($existing !== null) {
            $existing->tradeIds = $tradeIds;
            $existing->note = $note;
            $existing->updatedAt = $now;
            if ($existing->status === RentalTechnicianStatus::Declined) {
                $existing->status = $requiresConfirmation ? RentalTechnicianStatus::Requested : RentalTechnicianStatus::Assigned;
                $existing->requestedAt = $now;
                $existing->respondedAt = null;
                $existing->reminderCount = 0;
            } else {
                $notify = false; // nur Gewerke/Hinweis geändert
            }
            $assignment = $existing;
        } else {
            $assignment = new RentalTechnician(
                Uuid::v4(), $command->rentalId, $user->id,
                $requiresConfirmation ? RentalTechnicianStatus::Requested : RentalTechnicianStatus::Assigned,
                $tradeIds, $note, $now, null, 0, null, $now, $now,
            );
        }
        $this->assignments->save($assignment);

        $notification = $notify && $this->notifier !== null ? $this->notifier->notify($assignment->id) : null;

        return ['id' => $assignment->id, 'status' => $assignment->status, 'notification' => $notification];
    }
}
