<?php

declare(strict_types=1);

namespace App\Application\ClosingTextTemplate\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\ClosingTextTemplate\ClosingTextTemplateFolder;
use App\Domain\ClosingTextTemplate\ClosingTextTemplateFolderRepositoryInterface;
use App\Domain\Exception\ValidationException;
use App\Shared\Uuid;
use DateTimeImmutable;

final class CreateClosingTextTemplateFolderHandler implements CommandHandlerInterface
{
    public function __construct(private readonly ClosingTextTemplateFolderRepositoryInterface $folders)
    {
    }

    public function handle(CommandInterface $command): string
    {
        assert($command instanceof CreateClosingTextTemplateFolder);

        $name = trim($command->name);
        if ($name === '') {
            throw new ValidationException(['name' => 'Bitte einen Namen angeben.']);
        }

        if ($command->parentId !== null && $this->folders->findById($command->parentId) === null) {
            throw new ValidationException(['parentId' => 'Der gewählte Elternordner existiert nicht.']);
        }

        if ($this->folders->existsByNameAndParent($name, $command->parentId)) {
            throw new ValidationException(['name' => 'Dieser Ordner existiert unter demselben Elternordner bereits.']);
        }

        $now = new DateTimeImmutable();
        $folder = new ClosingTextTemplateFolder(
            id: Uuid::v4(),
            name: $name,
            parentId: $command->parentId,
            createdAt: $now,
            updatedAt: $now,
        );

        $this->folders->save($folder);

        return $folder->id;
    }
}
