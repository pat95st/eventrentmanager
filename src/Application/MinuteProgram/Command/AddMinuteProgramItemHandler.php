<?php

declare(strict_types=1);

namespace App\Application\MinuteProgram\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\MinuteProgram\MinuteProgramItem;
use App\Domain\MinuteProgram\MinuteProgramItemRepositoryInterface;
use App\Domain\MinuteProgram\MinuteProgramRepositoryInterface;
use App\Shared\Uuid;

final class AddMinuteProgramItemHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly MinuteProgramItemRepositoryInterface $items,
        private readonly MinuteProgramRepositoryInterface $programs,
    ) {
    }

    public function handle(CommandInterface $command): string
    {
        assert($command instanceof AddMinuteProgramItem);

        if ($this->programs->findById($command->minuteProgramId) === null) {
            throw new NotFoundException("Minutenprogramm \"{$command->minuteProgramId}\" wurde nicht gefunden.");
        }

        $name = trim($command->name);
        if ($name === '') {
            throw new ValidationException(['name' => 'Bitte einen Namen für den Programmpunkt angeben.']);
        }

        $item = new MinuteProgramItem(
            id: Uuid::v4(),
            minuteProgramId: $command->minuteProgramId,
            sortOrder: $this->items->nextSortOrder($command->minuteProgramId),
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
