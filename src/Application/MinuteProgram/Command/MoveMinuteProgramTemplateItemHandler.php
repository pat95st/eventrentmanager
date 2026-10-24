<?php

declare(strict_types=1);

namespace App\Application\MinuteProgram\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\MinuteProgram\MinuteProgramTemplateItemRepositoryInterface;

final class MoveMinuteProgramTemplateItemHandler implements CommandHandlerInterface
{
    public function __construct(private readonly MinuteProgramTemplateItemRepositoryInterface $items)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof MoveMinuteProgramTemplateItem);

        $item = $this->items->findById($command->itemId);
        if ($item === null) {
            throw new NotFoundException("Programmpunkt \"{$command->itemId}\" wurde nicht gefunden.");
        }

        $siblings = $this->items->findByTemplate($item->templateId);
        $position = null;
        foreach ($siblings as $index => $sibling) {
            if ($sibling->id === $item->id) {
                $position = $index;
                break;
            }
        }

        if ($position === null) {
            return null;
        }

        $neighborPosition = $command->direction === 'up' ? $position - 1 : $position + 1;
        if (!isset($siblings[$neighborPosition])) {
            return null;
        }

        $neighbor = $siblings[$neighborPosition];
        [$item->sortOrder, $neighbor->sortOrder] = [$neighbor->sortOrder, $item->sortOrder];

        $this->items->save($item);
        $this->items->save($neighbor);

        return null;
    }
}
