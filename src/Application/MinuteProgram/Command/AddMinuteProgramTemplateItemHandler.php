<?php

declare(strict_types=1);

namespace App\Application\MinuteProgram\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\MinuteProgram\MinuteProgramTemplateItem;
use App\Domain\MinuteProgram\MinuteProgramTemplateItemRepositoryInterface;
use App\Domain\MinuteProgram\MinuteProgramTemplateRepositoryInterface;
use App\Shared\Uuid;

final class AddMinuteProgramTemplateItemHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly MinuteProgramTemplateItemRepositoryInterface $items,
        private readonly MinuteProgramTemplateRepositoryInterface $templates,
    ) {
    }

    public function handle(CommandInterface $command): string
    {
        assert($command instanceof AddMinuteProgramTemplateItem);

        if ($this->templates->findById($command->templateId) === null) {
            throw new NotFoundException("Vorlage \"{$command->templateId}\" wurde nicht gefunden.");
        }

        $name = trim($command->name);
        if ($name === '') {
            throw new ValidationException(['name' => 'Bitte einen Namen für den Programmpunkt angeben.']);
        }

        $item = new MinuteProgramTemplateItem(
            id: Uuid::v4(),
            templateId: $command->templateId,
            sortOrder: $this->items->nextSortOrder($command->templateId),
            name: $name,
            description: self::normalize($command->description),
            noteGeneral: self::normalize($command->noteGeneral),
            noteSound: self::normalize($command->noteSound),
            noteLight: self::normalize($command->noteLight),
            noteVideo: self::normalize($command->noteVideo),
            noteStage: self::normalize($command->noteStage),
            durationMinutes: $command->durationMinutes !== null ? max(0, $command->durationMinutes) : null,
        );

        $this->items->save($item);

        return $item->id;
    }

    private static function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
