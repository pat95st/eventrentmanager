<?php

declare(strict_types=1);

namespace App\Application\MinuteProgram\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\MinuteProgram\MinuteProgramItemRepositoryInterface;
use App\Domain\MinuteProgram\MinuteProgramRepositoryInterface;
use App\Domain\MinuteProgram\MinuteProgramTemplate;
use App\Domain\MinuteProgram\MinuteProgramTemplateItem;
use App\Domain\MinuteProgram\MinuteProgramTemplateItemRepositoryInterface;
use App\Domain\MinuteProgram\MinuteProgramTemplateRepositoryInterface;
use App\Shared\Uuid;
use DateTimeImmutable;

/**
 * Macht aus einem bestehenden, konkreten Minutenprogramm eine wiederverwendbare
 * Vorlage — übernimmt die Kopfdaten (Jingle/Mikros) und alle Programmpunkte
 * 1:1, aber NICHT Veranstaltung/Raum/Startzeit (siehe MinuteProgramTemplate-Klassen-Doc).
 */
final class CreateMinuteProgramTemplateFromProgramHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly MinuteProgramRepositoryInterface $programs,
        private readonly MinuteProgramItemRepositoryInterface $items,
        private readonly MinuteProgramTemplateRepositoryInterface $templates,
        private readonly MinuteProgramTemplateItemRepositoryInterface $templateItems,
    ) {
    }

    public function handle(CommandInterface $command): string
    {
        assert($command instanceof CreateMinuteProgramTemplateFromProgram);

        $program = $this->programs->findById($command->minuteProgramId);
        if ($program === null) {
            throw new NotFoundException("Minutenprogramm \"{$command->minuteProgramId}\" wurde nicht gefunden.");
        }

        $name = trim($command->name);
        if ($name === '') {
            throw new ValidationException(['name' => 'Bitte einen Namen für die Vorlage angeben.']);
        }

        $now = new DateTimeImmutable();
        $template = new MinuteProgramTemplate(
            id: Uuid::v4(),
            name: $name,
            jingleBefore: $program->jingleBefore,
            handheldMicCount: $program->handheldMicCount,
            headsetMicCount: $program->headsetMicCount,
            createdAt: $now,
            updatedAt: $now,
        );
        $this->templates->save($template);

        foreach ($this->items->findByMinuteProgram($program->id) as $item) {
            $templateItem = new MinuteProgramTemplateItem(
                id: Uuid::v4(),
                templateId: $template->id,
                sortOrder: $item->sortOrder,
                name: $item->name,
                description: $item->description,
                noteGeneral: $item->noteGeneral,
                noteSound: $item->noteSound,
                noteLight: $item->noteLight,
                noteVideo: $item->noteVideo,
                noteStage: $item->noteStage,
                durationMinutes: $item->durationMinutes,
            );
            $this->templateItems->save($templateItem);
        }

        return $template->id;
    }
}
