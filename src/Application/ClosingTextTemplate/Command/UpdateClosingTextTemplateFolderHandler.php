<?php

declare(strict_types=1);

namespace App\Application\ClosingTextTemplate\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\ClosingTextTemplate\ClosingTextTemplateFolderRepositoryInterface;
use App\Domain\Exception\NotFoundException;
use App\Domain\Exception\ValidationException;
use DateTimeImmutable;

final class UpdateClosingTextTemplateFolderHandler implements CommandHandlerInterface
{
    public function __construct(private readonly ClosingTextTemplateFolderRepositoryInterface $folders)
    {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof UpdateClosingTextTemplateFolder);

        $folder = $this->folders->findById($command->id);
        if ($folder === null) {
            throw new NotFoundException("Ordner \"{$command->id}\" wurde nicht gefunden.");
        }

        $name = trim($command->name);
        if ($name === '') {
            throw new ValidationException(['name' => 'Bitte einen Namen angeben.']);
        }

        if ($command->parentId === $command->id) {
            throw new ValidationException(['parentId' => 'Ein Ordner kann nicht sein eigener Elternordner sein.']);
        }

        if ($command->parentId !== null) {
            if ($this->folders->findById($command->parentId) === null) {
                throw new ValidationException(['parentId' => 'Der gewählte Elternordner existiert nicht.']);
            }
            if (in_array($command->parentId, $this->folders->findDescendantIds($command->id), true)) {
                throw new ValidationException(['parentId' => 'Ein Ordner kann nicht einem seiner eigenen Unterordner untergeordnet werden.']);
            }
        }

        if ($this->folders->existsByNameAndParent($name, $command->parentId, $command->id)) {
            throw new ValidationException(['name' => 'Dieser Ordner existiert unter demselben Elternordner bereits.']);
        }

        $folder->name = $name;
        $folder->parentId = $command->parentId;
        $folder->updatedAt = new DateTimeImmutable();

        $this->folders->save($folder);

        return null;
    }
}
