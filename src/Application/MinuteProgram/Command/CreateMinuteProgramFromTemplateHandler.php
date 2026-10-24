<?php

declare(strict_types=1);

namespace App\Application\MinuteProgram\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\MinuteProgram\MinuteProgram;
use App\Domain\MinuteProgram\MinuteProgramItem;
use App\Domain\MinuteProgram\MinuteProgramItemRepositoryInterface;
use App\Domain\MinuteProgram\MinuteProgramRepositoryInterface;
use App\Domain\MinuteProgram\MinuteProgramTemplateItemRepositoryInterface;
use App\Domain\MinuteProgram\MinuteProgramTemplateRepositoryInterface;
use App\Domain\Rental\RentalRepositoryInterface;
use App\Shared\Uuid;
use DateTimeImmutable;

/**
 * Erzeugt für eine Veranstaltung ein konkretes Minutenprogramm aus einer
 * Vorlage — übernimmt Kopfdaten (Jingle/Mikros) und alle Programmpunkte
 * 1:1 aus der Vorlage, Raum/Startzeit werden hier (veranstaltungsspezifisch)
 * neu vergeben. Spiegelbildlich zu CreateMinuteProgramTemplateFromProgram.
 */
final class CreateMinuteProgramFromTemplateHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly MinuteProgramTemplateRepositoryInterface $templates,
        private readonly MinuteProgramTemplateItemRepositoryInterface $templateItems,
        private readonly MinuteProgramRepositoryInterface $programs,
        private readonly MinuteProgramItemRepositoryInterface $items,
        private readonly RentalRepositoryInterface $rentals,
    ) {
    }

    public function handle(CommandInterface $command): string
    {
        assert($command instanceof CreateMinuteProgramFromTemplate);

        $template = $this->templates->findById($command->templateId);
        if ($template === null) {
            throw new NotFoundException("Vorlage \"{$command->templateId}\" wurde nicht gefunden.");
        }

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
            jingleBefore: $template->jingleBefore,
            handheldMicCount: $template->handheldMicCount,
            headsetMicCount: $template->headsetMicCount,
            currentDelayMinutes: 0,
            createdAt: $now,
            updatedAt: $now,
        );
        $this->programs->save($program);

        foreach ($this->templateItems->findByTemplate($template->id) as $templateItem) {
            $item = new MinuteProgramItem(
                id: Uuid::v4(),
                minuteProgramId: $program->id,
                sortOrder: $templateItem->sortOrder,
                name: $templateItem->name,
                description: $templateItem->description,
                noteGeneral: $templateItem->noteGeneral,
                noteSound: $templateItem->noteSound,
                noteLight: $templateItem->noteLight,
                noteVideo: $templateItem->noteVideo,
                noteStage: $templateItem->noteStage,
                durationMinutes: $templateItem->durationMinutes,
            );
            $this->items->save($item);
        }

        return $program->id;
    }
}
