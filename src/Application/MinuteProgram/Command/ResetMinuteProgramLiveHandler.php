<?php

declare(strict_types=1);

namespace App\Application\MinuteProgram\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\MinuteProgram\MinuteProgramRepositoryInterface;
use DateTimeImmutable;

final class ResetMinuteProgramLiveHandler implements CommandHandlerInterface
{
    public function __construct(private readonly MinuteProgramRepositoryInterface $programs)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof ResetMinuteProgramLive);

        $program = $this->programs->findById($command->minuteProgramId);
        if ($program === null) {
            throw new NotFoundException("Minutenprogramm \"{$command->minuteProgramId}\" wurde nicht gefunden.");
        }
        $program->resetLive();
        $program->updatedAt = new DateTimeImmutable();
        $this->programs->save($program);

        return null;
    }
}
