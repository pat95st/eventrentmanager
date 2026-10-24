<?php

declare(strict_types=1);

namespace App\Application\MinuteProgram\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\MinuteProgram\MinuteProgramTemplateItemRepositoryInterface;

final class UpdateMinuteProgramTemplateItemHandler implements CommandHandlerInterface
{
    public function __construct(private readonly MinuteProgramTemplateItemRepositoryInterface $items)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof UpdateMinuteProgramTemplateItem);

        $item = $this->items->findById($command->itemId);
        if ($item === null) {
            throw new NotFoundException("Programmpunkt \"{$command->itemId}\" wurde nicht gefunden.");
        }

        $name = trim($command->name);
        if ($name === '') {
            throw new ValidationException(['name' => 'Bitte einen Namen für den Programmpunkt angeben.']);
        }

        $item->name = $name;
        $item->description = self::normalize($command->description);
        $item->noteGeneral = self::normalize($command->noteGeneral);
        $item->noteSound = self::normalize($command->noteSound);
        $item->noteLight = self::normalize($command->noteLight);
        $item->noteVideo = self::normalize($command->noteVideo);
        $item->noteStage = self::normalize($command->noteStage);
        $item->durationMinutes = $command->durationMinutes !== null ? max(0, $command->durationMinutes) : null;

        $this->items->save($item);

        return null;
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
