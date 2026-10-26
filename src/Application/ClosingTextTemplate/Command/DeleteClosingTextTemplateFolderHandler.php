<?php

declare(strict_types=1);

namespace App\Application\ClosingTextTemplate\Command;

use App\Application\Command\CommandHandlerInterface;
use App\Application\Command\CommandInterface;
use App\Domain\ClosingTextTemplate\ClosingTextTemplateFolderRepositoryInterface;
use App\Domain\ClosingTextTemplate\ClosingTextTemplateRepositoryInterface;
use App\Domain\Exception\ConflictException;
use App\Domain\Exception\NotFoundException;

final class DeleteClosingTextTemplateFolderHandler implements CommandHandlerInterface
{
    public function __construct(
        private readonly ClosingTextTemplateFolderRepositoryInterface $folders,
        private readonly ClosingTextTemplateRepositoryInterface $templates,
    ) {
    }

    public function handle(CommandInterface $command): mixed
    {
        assert($command instanceof DeleteClosingTextTemplateFolder);

        $folder = $this->folders->findById($command->id);
        if ($folder === null) {
            throw new NotFoundException("Ordner \"{$command->id}\" wurde nicht gefunden.");
        }

        if ($this->folders->findChildren($command->id) !== []) {
            throw new ConflictException('Dieser Ordner enthält noch Unterordner. Bitte zuerst verschieben oder löschen.');
        }

        if ($this->templates->countByFolder($command->id) > 0) {
            throw new ConflictException('Diesem Ordner sind noch Textvorlagen zugeordnet. Bitte zuerst verschieben oder löschen.');
        }

        $this->folders->delete($command->id);

        return null;
    }
}
