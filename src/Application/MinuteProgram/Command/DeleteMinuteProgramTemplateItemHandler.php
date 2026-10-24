<?php

declare(strict_types=1);

namespace App\Application\MinuteProgram\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\MinuteProgram\MinuteProgramTemplateItemRepositoryInterface;

final class DeleteMinuteProgramTemplateItemHandler implements CommandHandlerInterface
{
    public function __construct(private readonly MinuteProgramTemplateItemRepositoryInterface $items)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof DeleteMinuteProgramTemplateItem);

        if ($this->items->findById($command->itemId) === null) {
            throw new NotFoundException("Programmpunkt \"{$command->itemId}\" wurde nicht gefunden.");
        }

        $this->items->delete($command->itemId);

        return null;
    }
}
