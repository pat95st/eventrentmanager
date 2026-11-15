<?php

declare(strict_types=1);

namespace App\Application\MinuteProgram\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\MinuteProgram\MinuteProgramItemRepositoryInterface;
use App\Domain\MinuteProgram\MinuteProgramRepositoryInterface;
use App\Domain\MinuteProgram\MinuteProgramSchedule;
use DateTimeImmutable;

final class StartMinuteProgramItemHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly MinuteProgramRepositoryInterface $programs,
        private readonly MinuteProgramItemRepositoryInterface $items,
    ) {
    }

    /** @return int the resulting delay in seconds */
    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof StartMinuteProgramItem);

        $program = $this->programs->findById($command->minuteProgramId);
        if ($program === null) {
            throw new NotFoundException("Minutenprogramm \"{$command->minuteProgramId}\" wurde nicht gefunden.");
        }
        $offsets = MinuteProgramSchedule::plannedOffsets($this->items->findByMinuteProgram($program->id));
        if (!isset($offsets[$command->itemId])) {
            throw new NotFoundException('Der Programmpunkt gehört nicht zu diesem Minutenprogramm.');
        }

        $startedAt = $command->startedAt ?? new DateTimeImmutable();
        $plannedStart = $program->startAt->modify('+' . $offsets[$command->itemId] . ' minutes');

        $program->currentDelaySeconds = MinuteProgramLiveClock::clampDelay($startedAt->getTimestamp() - $plannedStart->getTimestamp());
        $program->liveItemId = $command->itemId;
        $program->liveItemStartedAt = $startedAt;
        $program->liveFinishedAt = null;
        $program->updatedAt = new DateTimeImmutable();
        $this->programs->save($program);

        return $program->currentDelaySeconds;
    }
}
