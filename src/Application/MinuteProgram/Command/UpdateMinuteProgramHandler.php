<?php

declare(strict_types=1);

namespace App\Application\MinuteProgram\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\MinuteProgram\MinuteProgramRepositoryInterface;
use DateTimeImmutable;

final class UpdateMinuteProgramHandler implements CommandHandlerInterface
{
    public function __construct(private readonly MinuteProgramRepositoryInterface $programs)
    {
    }

    public function handle(CommandInterface $command): void
    {
        assert($command instanceof UpdateMinuteProgram);

        $program = $this->programs->findById($command->minuteProgramId);
        if ($program === null) {
            throw new NotFoundException("Minutenprogramm \"{$command->minuteProgramId}\" wurde nicht gefunden.");
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

        $program->name = $name;
        $program->room = $command->room !== null && trim($command->room) !== '' ? trim($command->room) : null;
        $program->startAt = $startAt;
        $program->jingleBefore = $command->jingleBefore;
        $program->handheldMicCount = max(0, $command->handheldMicCount);
        $program->headsetMicCount = max(0, $command->headsetMicCount);
        $program->updatedAt = new DateTimeImmutable();

        $this->programs->save($program);
    }
}
