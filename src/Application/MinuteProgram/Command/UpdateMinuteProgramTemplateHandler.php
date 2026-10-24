<?php

declare(strict_types=1);

namespace App\Application\MinuteProgram\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use App\Domain\MinuteProgram\MinuteProgramTemplateRepositoryInterface;
use DateTimeImmutable;

final class UpdateMinuteProgramTemplateHandler implements CommandHandlerInterface
{
    public function __construct(private readonly MinuteProgramTemplateRepositoryInterface $templates)
    {
    }

    public function handle(CommandInterface $command): void
    {
        assert($command instanceof UpdateMinuteProgramTemplate);

        $template = $this->templates->findById($command->templateId);
        if ($template === null) {
            throw new NotFoundException("Vorlage \"{$command->templateId}\" wurde nicht gefunden.");
        }

        $name = trim($command->name);
        if ($name === '') {
            throw new ValidationException(['name' => 'Bitte einen Namen für die Vorlage angeben.']);
        }

        $template->name = $name;
        $template->jingleBefore = $command->jingleBefore;
        $template->handheldMicCount = max(0, $command->handheldMicCount);
        $template->headsetMicCount = max(0, $command->headsetMicCount);
        $template->updatedAt = new DateTimeImmutable();

        $this->templates->save($template);
    }
}
