<?php

declare(strict_types=1);

namespace App\Application\MinuteProgram\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\MinuteProgram\MinuteProgramTemplateItemRepositoryInterface;
use App\Domain\MinuteProgram\MinuteProgramTemplateRepositoryInterface;

final class DeleteMinuteProgramTemplateHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly MinuteProgramTemplateRepositoryInterface $templates,
        private readonly MinuteProgramTemplateItemRepositoryInterface $items,
    ) {
    }

    public function handle(CommandInterface $command): void
    {
        assert($command instanceof DeleteMinuteProgramTemplate);

        $template = $this->templates->findById($command->templateId);
        if ($template === null) {
            throw new NotFoundException("Vorlage \"{$command->templateId}\" wurde nicht gefunden.");
        }

        foreach ($this->items->findByTemplate($template->id) as $item) {
            $this->items->delete($item->id);
        }

        $this->templates->delete($template->id);
    }
}
