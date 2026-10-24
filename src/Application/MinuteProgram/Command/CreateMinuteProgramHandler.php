<?php

declare(strict_types=1);

namespace App\Application\MinuteProgram\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\MinuteProgram\MinuteProgram;
use App\Domain\MinuteProgram\MinuteProgramRepositoryInterface;
use App\Domain\Rental\RentalRepositoryInterface;
use App\Shared\Uuid;
use DateTimeImmutable;

final class CreateMinuteProgramHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly MinuteProgramRepositoryInterface $programs,
        private readonly RentalRepositoryInterface $rentals,
    ) {
    }

    public function handle(CommandInterface $command): string
    {
        assert($command instanceof CreateMinuteProgram);

        if ($this->rentals->findById($command->rentalId) === null) {
            throw new NotFoundException("Veranstaltung \"{$command->rentalId}\" wurde nicht gefunden.");
        }

        $errors = [];
        $name = trim($command->name);
        if ($name === '') {
            $errors['name'] = 'Bitte einen Namen angeben.';
        }

        $startAt = DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $command->startAt)
            ?: DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $command->startAt)
            ?: DateTimeImmutable::createFromFormat('Y-m-d H:i', $command->startAt);
        if ($startAt === false) {
            $errors['startAt'] = 'Bitte einen gültigen Startzeitpunkt angeben.';
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        $now = new DateTimeImmutable();
        $program = new MinuteProgram(
            id: Uuid::v4(),
            rentalId: $command->rentalId,
            name: $name,
            room: $command->room !== null && trim($command->room) !== '' ? trim($command->room) : null,
            startAt: $startAt,
            jingleBefore: $command->jingleBefore,
            handheldMicCount: max(0, $command->handheldMicCount),
            headsetMicCount: max(0, $command->headsetMicCount),
            currentDelayMinutes: 0,
            createdAt: $now,
            updatedAt: $now,
        );

        $this->programs->save($program);

        return $program->id;
    }
}
