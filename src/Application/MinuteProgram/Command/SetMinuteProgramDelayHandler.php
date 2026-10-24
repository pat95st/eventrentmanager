<?php

declare(strict_types=1);

namespace App\Application\MinuteProgram\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\MinuteProgram\MinuteProgramRepositoryInterface;
use DateTimeImmutable;

/**
 * Setzt die aktuelle Verzögerung (in Minuten, auch negativ für "vorgezogen")
 * live während der Veranstaltung. Verändert NICHT den ursprünglichen Plan
 * ($startAt, Dauer der Programmpunkte), nur die berechnete Live-Anzeige
 * (siehe MinuteProgram::delayedStartAt() und MinuteProgramDetailBuilder).
 */
final class SetMinuteProgramDelayHandler implements CommandHandlerInterface
{
    public function __construct(private readonly MinuteProgramRepositoryInterface $programs)
    {
    }

    public function handle(CommandInterface $command): void
    {
        assert($command instanceof SetMinuteProgramDelay);

        $program = $this->programs->findById($command->minuteProgramId);
        if ($program === null) {
            throw new NotFoundException("Minutenprogramm \"{$command->minuteProgramId}\" wurde nicht gefunden.");
        }

        $program->currentDelayMinutes = $command->currentDelayMinutes;
        $program->updatedAt = new DateTimeImmutable();

        $this->programs->save($program);
    }
}
