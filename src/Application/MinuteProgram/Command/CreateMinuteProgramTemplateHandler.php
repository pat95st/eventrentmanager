<?php

declare(strict_types=1);

namespace App\Application\MinuteProgram\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\Exception\ValidationException;
use App\Domain\MinuteProgram\MinuteProgramTemplate;
use App\Domain\MinuteProgram\MinuteProgramTemplateRepositoryInterface;
use App\Shared\Uuid;
use DateTimeImmutable;

final class CreateMinuteProgramTemplateHandler implements CommandHandlerInterface
{
    public function __construct(private readonly MinuteProgramTemplateRepositoryInterface $templates)
    {
    }

    public function handle(CommandInterface $command): string
    {
        assert($command instanceof CreateMinuteProgramTemplate);

        $name = trim($command->name);
        if ($name === '') {
            throw new ValidationException(['name' => 'Bitte einen Namen für die Vorlage angeben.']);
        }

        $now = new DateTimeImmutable();
        $template = new MinuteProgramTemplate(
            id: Uuid::v4(),
            name: $name,
            jingleBefore: $command->jingleBefore,
            handheldMicCount: max(0, $command->handheldMicCount),
            headsetMicCount: max(0, $command->headsetMicCount),
            createdAt: $now,
            updatedAt: $now,
        );

        $this->templates->save($template);

        return $template->id;
    }
}
